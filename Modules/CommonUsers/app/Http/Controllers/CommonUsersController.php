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
use Modules\Agreements\Models\Agreement;
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
use Modules\Payments\Models\Payment;
use Modules\Payments\Http\Resources\PaymentResource;

class CommonUsersController extends Controller
{
    use ApiResponse;

    public function __construct(protected CommonUserService $service)
    {
    }

    public function index(Request $request)
    {
        if ($request->query('archived') === 'only') {
            $query = CommonUser::onlyTrashed()->with('profilePhoto')->withCount(['documents', 'verifiedDocuments']);

            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            if ($country = $request->query('country')) {
                $query->where('country', $country);
            }

            return $this->ok(CommonUserResource::collection(
                $query->latest()->paginate((int) $request->integer('per_page', 15))->withQueryString()
            ));
        }

        $commonUsers = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            filters: $request->only(['search', 'status', 'service_category', 'country']),
        );

        $commonUsers->getCollection()->load('profilePhoto');

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
        $commonUser->load('profilePhoto')->loadCount(['documents', 'verifiedDocuments']);

        return $this->ok(new CommonUserResource($commonUser));
    }

    public function update(UpdateCommonUserRequest $request, CommonUser $commonUser)
    {
        $commonUser = $this->service->update($commonUser, $request->validated());

        return $this->ok(new CommonUserResource($commonUser), 'Common user updated successfully.');
    }

    public function destroy(Request $request, CommonUser $commonUser, FolderService $folderService)
    {
        DB::transaction(function () use ($request, $commonUser, $folderService) {
            $folderService->archiveDeletedLeadFolderTree($commonUser, $request->user()->id);
            $this->service->delete($commonUser);
        });

        return $this->noContent();
    }

    public function restore(Request $request, int $commonUserId, FolderService $folderService)
    {
        $commonUser = CommonUser::withTrashed()->findOrFail($commonUserId);

        DB::transaction(function () use ($request, $commonUser, $folderService) {
            $commonUser->restore();
            $folderService->restoreLeadFolderTree($commonUser->refresh(), $request->user()->id);
        });

        return $this->ok(new CommonUserResource($commonUser->refresh()->loadCount(['documents', 'verifiedDocuments'])), 'Common user restored successfully.');
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

    public function uploadProfilePhoto(Request $request, CommonUser $commonUser, FileService $fileService, FolderService $folderService)
    {
        $request->validate([
            'file' => ['required', 'image', 'max:5120', 'mimes:jpeg,jpg,png,webp'],
        ]);

        $file = DB::transaction(function () use ($request, $commonUser, $fileService, $folderService) {
            $folder = $folderService->leadSubfolder($commonUser, 'Profile Photo', $request->user()->id);
            $file = $fileService->uploadForLead($request->file('file'), $commonUser->id, $request->user()->id, $folder->id);

            $commonUser->forceFill(['profile_photo_file_id' => $file->id])->save();

            return $file;
        });

        return $this->created(new FileResource($file), 'Profile photo updated.');
    }

    public function recordPayment(Request $request, CommonUser $commonUser, PaymentService $paymentService, FileService $fileService, FolderService $folderService)
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['nullable', 'string', 'max:80'],
            'reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'paid_at' => ['nullable', 'date'],
            'receipt' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $payment = DB::transaction(function () use ($request, $commonUser, $paymentService, $fileService, $folderService, $validated) {
            $agreement = Agreement::where('common_user_id', $commonUser->id)->latest()->first();
            $payment = $paymentService->create([
                'common_user_id' => $commonUser->id,
                'agreement_id' => $agreement?->id,
                'amount' => $validated['amount'],
                'method' => $validated['method'] ?? 'bank',
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? 'Lead advance payment before client conversion.',
                'paid_at' => $validated['paid_at'] ?? now()->toDateString(),
                'status' => 'verified',
                'verified_at' => now(),
                'verified_by' => $request->user()->id,
                'recorded_by' => $request->user()->id,
            ]);

            $folder = $folderService->leadSubfolder($commonUser, 'Payments', $request->user()->id);
            $receipt = $fileService->uploadForLead($request->file('receipt'), $commonUser->id, $request->user()->id, $folder->id);
            $receipt->forceFill([
                'verified' => true,
                'verified_at' => now(),
                'verified_by' => $request->user()->id,
            ])->save();
            $payment->forceFill(['receipt_file_id' => $receipt->id])->save();

            $commonUser->forceFill([
                'paid_amount' => (int) Payment::where('common_user_id', $commonUser->id)->where('status', 'verified')->sum('amount'),
            ])->save();
            $this->service->update($commonUser->refresh(), []);

            return $payment->refresh();
        });

        return $this->created(new PaymentResource($payment->load('receiptFile')), 'Lead payment recorded and receipt verified.');
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

        if (! Agreement::where('common_user_id', $commonUser->id)->whereNotNull('signed_file_id')->where('status', 'signed')->exists()) {
            throw ValidationException::withMessages([
                'agreement' => ['Upload and verify the signed agreement before converting to a client.'],
            ]);
        }

        if (! Payment::where('common_user_id', $commonUser->id)->where('status', 'verified')->whereNotNull('receipt_file_id')->exists()) {
            throw ValidationException::withMessages([
                'payment' => ['Record and verify a partial payment receipt before converting to a client.'],
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
                'profile_photo_file_id' => $commonUser->profile_photo_file_id,
                'created_by' => $request->user()->id,
            ]);

            $folderService->createClientFolderTree($client, $request->user()->id);

            $clientFolders = [
                'Unsigned Agreement' => $folderService->clientSubfolder($client, 'Unsigned Agreement', $request->user()->id)->id,
                'Signed Agreement' => $folderService->clientSubfolder($client, 'Signed Agreement', $request->user()->id)->id,
                'Payments' => $folderService->clientSubfolder($client, 'Payments', $request->user()->id)->id,
                'Profile Photo' => $folderService->clientSubfolder($client, 'Profile Photo', $request->user()->id)->id,
                'Applicant Documents' => $folderService->clientSubfolder($client, 'Applicant Documents', $request->user()->id)->id,
            ];

            File::where('common_user_id', $commonUser->id)->with('folder')->get()->each(function (File $file) use ($client, $clientFolders) {
                $sourceName = $file->folder?->name;
                $targetFolderId = $clientFolders[$sourceName] ?? $clientFolders['Applicant Documents'];
                $file->forceFill([
                    'client_id' => $client->id,
                    'common_user_id' => null,
                    'folder_id' => $targetFolderId,
                ])->save();
            });

            Payment::where('common_user_id', $commonUser->id)->update([
                'client_id' => $client->id,
                'common_user_id' => null,
            ]);

            Agreement::where('common_user_id', $commonUser->id)->update([
                'client_id' => $client->id,
                'common_user_id' => null,
            ]);

            $client->forceFill(['paid_amount' => (int) Payment::where('client_id', $client->id)->sum('amount')])->save();

            $this->service->update($commonUser, ['status' => 'converted']);

            // The lead's own folder tree is now empty of live documents - move
            // it into Moved > Common Users rather than leaving it sitting in
            // the active lead tree or the deleted-user archive.
            $folderService->moveConvertedLeadFolderTree($commonUser, $request->user()->id);

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
