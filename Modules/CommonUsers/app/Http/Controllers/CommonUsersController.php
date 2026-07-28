<?php

namespace Modules\CommonUsers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\ClientService;
use Modules\CommonUsers\Http\Requests\StoreCommonUserRequest;
use Modules\CommonUsers\Http\Requests\StoreLeadDocumentRequest;
use Modules\CommonUsers\Http\Requests\UpdateCommonUserRequest;
use Modules\CommonUsers\Http\Resources\CommonUserResource;
use Modules\CommonUsers\Models\CommonUser;
use Modules\CommonUsers\Services\CommonUserService;
use Modules\Files\Http\Resources\FileResource;
use Modules\Files\Models\File;
use Modules\Files\Services\FileService;
use Modules\Folders\Services\FolderService;
use Modules\Payments\Services\PaymentService;

class CommonUsersController extends Controller
{
    use ApiResponse;

    public function __construct(protected CommonUserService $service)
    {
    }

    public function index(Request $request)
    {
        $commonUsers = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            filters: $request->only(['search', 'status', 'service_category', 'country']),
        );

        return $this->ok(CommonUserResource::collection($commonUsers));
    }

    public function store(StoreCommonUserRequest $request, FolderService $folderService)
    {
        $commonUser = DB::transaction(function () use ($request, $folderService) {
            $commonUser = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);
            $folderService->createLeadFolderTree($commonUser, $request->user()->id);

            return $commonUser;
        });

        return $this->created(new CommonUserResource($commonUser));
    }

    public function show(CommonUser $commonUser)
    {
        $commonUser->loadCount(['documents', 'verifiedDocuments']);

        return $this->ok(new CommonUserResource($commonUser));
    }

    public function update(UpdateCommonUserRequest $request, CommonUser $commonUser)
    {
        $commonUser = $this->service->update($commonUser, $request->validated());

        return $this->ok(new CommonUserResource($commonUser), 'Common user updated successfully.');
    }

    public function destroy(CommonUser $commonUser)
    {
        $this->service->delete($commonUser);

        return $this->noContent();
    }

    /** List the documents uploaded against this lead. */
    public function documents(CommonUser $commonUser)
    {
        $documents = $commonUser->documents()->latest()->get();

        return $this->ok(FileResource::collection($documents));
    }

    /** Upload a document for this lead (used to satisfy the conversion gate). */
    public function uploadDocument(StoreLeadDocumentRequest $request, CommonUser $commonUser, FileService $fileService, FolderService $folderService)
    {
        $folder = $folderService->leadSubfolder($commonUser, 'Applicant Documents', $request->user()->id);
        $file = $fileService->uploadForLead($request->file('file'), $commonUser->id, $request->user()->id, $folder->id);

        return $this->created(new FileResource($file));
    }

    /**
     * Convert a lead into an active Client case. Requires both a recorded
     * payment AND at least one verified document (per the spec: valid documents
     * are required for conversion even when only a partial payment is made).
     * On success the client's folder tree is created and the lead's documents
     * are re-filed into it.
     */
    public function convert(Request $request, CommonUser $commonUser, ClientService $clientService, PaymentService $paymentService, FolderService $folderService)
    {
        if ($commonUser->status === 'converted') {
            throw ValidationException::withMessages([
                'status' => ['This common user has already been converted to a client.'],
            ]);
        }

        if ($commonUser->paid_amount <= 0) {
            throw ValidationException::withMessages([
                'paid_amount' => ['At least a partial payment must be recorded before converting to a client.'],
            ]);
        }

        if (! $commonUser->documents()->where('verified', true)->exists()) {
            throw ValidationException::withMessages([
                'documents' => ['Upload and verify at least one document before converting to a client.'],
            ]);
        }

        return DB::transaction(function () use ($request, $commonUser, $clientService, $paymentService, $folderService) {
            $client = $clientService->create([
                'common_user_id' => $commonUser->id,
                'full_name' => $commonUser->full_name,
                'passport_no' => $commonUser->passport_no,
                'nic' => $commonUser->nic,
                'phone' => $commonUser->phone,
                'email' => $commonUser->email,
                'country' => $commonUser->country,
                'native_country' => $commonUser->native_country,
                'visa_type' => $commonUser->visa_type,
                'service_category' => $commonUser->service_category,
                'agreement_amount' => $commonUser->agreement_amount,
                'created_by' => $request->user()->id,
            ]);

            // Carry the advance across as a real payment record so the client's
            // balance stays the single-source-of-truth sum of its payments.
            $paymentService->create([
                'client_id' => $client->id,
                'amount' => $commonUser->paid_amount,
                'method' => 'advance',
                'reference' => 'Advance carried over from lead '.$commonUser->id,
                'notes' => 'Initial advance recorded at client conversion.',
                'paid_at' => now()->toDateString(),
                'recorded_by' => $request->user()->id,
            ]);

            $applicantDocumentsFolderId = $folderService->createClientFolderTree($client, $request->user()->id);

            // Re-file the lead's documents into the client's Applicant Documents folder.
            File::where('common_user_id', $commonUser->id)->update([
                'client_id' => $client->id,
                'folder_id' => $applicantDocumentsFolderId,
            ]);

            $this->service->update($commonUser, ['status' => 'converted']);

            app(\Modules\Communications\Services\AlertDispatcher::class)->trigger('client_converted', [
                'client_id' => $client->id,
                'client_reference' => $client->reference_no,
                'client_name' => $client->full_name,
                'service_category' => $client->service_category,
                'visa_type' => $client->visa_type,
                'country' => $client->country,
            ], "client-{$client->id}-converted");

            return $this->created(new ClientResource($client->refresh()), 'Converted to client successfully.');
        });
    }
}
