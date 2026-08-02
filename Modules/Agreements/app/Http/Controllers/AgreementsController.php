<?php

namespace Modules\Agreements\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Agreements\Http\Requests\StoreAgreementRequest;
use Modules\Agreements\Http\Resources\AgreementResource;
use Modules\Agreements\Models\Agreement;
use Modules\Agreements\Services\AgreementDocumentService;
use Modules\Agreements\Services\AgreementPackageService;
use Modules\Agreements\Services\AgreementPdfService;
use Modules\Agreements\Services\AgreementService;
use Modules\Communications\Http\Resources\MessageResource;
use Modules\Communications\Services\MessageService;
use Modules\CommonUsers\Models\CommonUser;
use Modules\Files\Services\FileService;
use Modules\Folders\Services\FolderService;

class AgreementsController extends Controller
{
    use ApiResponse;

    public function __construct(protected AgreementService $service)
    {
    }

    public function index(Request $request)
    {
        $agreements = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            with: ['generatedFile', 'signedFile'],
            filters: $request->only(['search', 'status', 'client_id', 'common_user_id']),
        );

        return $this->ok(AgreementResource::collection($agreements));
    }

    public function store(StoreAgreementRequest $request, AgreementDocumentService $documents)
    {
        $agreement = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return $this->created(new AgreementResource($agreement->load('generatedFile')));
    }

    public function show(Agreement $agreement)
    {
        return $this->ok(new AgreementResource($agreement->load('generatedFile')));
    }

    public function pdf(Agreement $agreement, AgreementPdfService $pdfService)
    {
        $pdf = $pdfService->render($agreement);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$agreement->reference_no.'-agreement.pdf"',
        ]);
    }

    public function generate(Request $request, Agreement $agreement, AgreementDocumentService $documents)
    {
        if ($agreement->generated_file_id) {
            throw ValidationException::withMessages([
                'agreement' => ['This agreement has already been generated and saved.'],
            ]);
        }

        $validated = $request->validate([
            'folder_id' => ['nullable', 'integer', 'exists:folders,id'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $documents->generate($agreement, isset($validated['folder_id']) ? (int) $validated['folder_id'] : null, $validated['file_name'] ?? null, $request->user()->id);

        app(\Modules\Communications\Services\AlertDispatcher::class)->trigger('agreement_generated', [
            'client_id' => $agreement->client_id,
            'agreement_reference' => $agreement->reference_no,
            'client_name' => $agreement->client_name,
        ], "agreement-{$agreement->id}-generated");

        return $this->created([
            'agreement' => new AgreementResource($agreement->refresh()->load('generatedFile')),
            'file' => new \Modules\Files\Http\Resources\FileResource($file),
        ], 'Agreement document generated and saved to folder.');
    }

    public function share(
        Request $request,
        Agreement $agreement,
        AgreementDocumentService $documents,
        AgreementPackageService $package,
        MessageService $messages,
        FileService $files,
    ) {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['whatsapp', 'email', 'sms'])],
            'recipient' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'welcome_message' => ['nullable', 'string'],
            'bank_instructions' => ['nullable', 'string'],
            'explainer_video_url' => ['nullable', 'url', 'max:2048'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,mp4,mov,webm'],
        ]);

        // Make sure an unsigned agreement PDF exists and include it in the same outgoing message.
        $file = $agreement->generatedFile ?: $documents->generate($agreement, null, null, $request->user()->id);
        $agreementLink = URL::temporarySignedRoute('api.files.signed-download', now()->addDays(7), $file->id);
        $videoUrl = trim((string) ($validated['explainer_video_url'] ?? '')) ?: ($package->defaultVideo()['url'] ?? null);
        $extraLink = null;

        if ($request->hasFile('attachment')) {
            $extra = $agreement->client_id
                ? $files->uploadForClientFolder($request->file('attachment'), (int) $agreement->client_id, (int) $file->folder_id, $request->user()->id)
                : $files->upload($request->file('attachment'), (int) $file->folder_id, $request->user()->id);
            $extraLink = URL::temporarySignedRoute('api.files.signed-download', now()->addDays(7), $extra->id);
        }

        $message = $messages->send([
            'channel' => $validated['channel'],
            'recipient' => $validated['recipient'],
            'client_id' => $agreement->client_id,
            'common_user_id' => $agreement->common_user_id,
            'workflow_step' => 'agreement',
            'subject' => $validated['subject'] ?? $agreement->reference_no.' Service Agreement',
            'body' => $package->buildShareBody(
                agreement: $agreement,
                agreementLink: $agreementLink,
                videoLink: $videoUrl,
                extraLink: $extraLink,
                welcome: $validated['welcome_message'] ?? null,
                bankInstructions: $validated['bank_instructions'] ?? null,
            ),
        ], $request->user()->id);

        if ($agreement->status === 'draft') {
            $agreement->forceFill(['status' => 'sent'])->save();
        }

        app(\Modules\Communications\Services\AlertDispatcher::class)->trigger('agreement_shared', [
            'client_id' => $agreement->client_id,
            'agreement_reference' => $agreement->reference_no,
            'client_name' => $agreement->client_name,
            'channel' => $validated['channel'],
        ], "agreement-{$agreement->id}-shared");

        return $this->created(new MessageResource($message), 'Agreement package shared and recorded.');
    }

    public function defaultVideo(AgreementPackageService $package)
    {
        $path = $package->defaultVideoPath();

        abort_if(! $path, 404, 'Default agreement video not found.');

        return response()->file($path, [
            'Content-Type' => mime_content_type($path) ?: 'video/mp4',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
        ]);
    }

    public function uploadSigned(Request $request, Agreement $agreement, FileService $files, FolderService $folders)
    {
        if (! $agreement->generated_file_id) {
            throw ValidationException::withMessages([
                'agreement' => ['Generate and save the unsigned agreement before uploading the signed copy.'],
            ]);
        }

        if ($agreement->signed_file_id) {
            throw ValidationException::withMessages([
                'file' => ['A signed agreement is already uploaded for this agreement.'],
            ]);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        if ($agreement->common_user_id) {
            $lead = CommonUser::findOrFail($agreement->common_user_id);
            $folder = $folders->leadSubfolder($lead, 'Signed Agreement', $request->user()->id);
            $file = $files->uploadForLead($request->file('file'), $lead->id, $request->user()->id, $folder->id);
        } elseif ($agreement->client_id) {
            $client = \Modules\Clients\Models\Client::findOrFail($agreement->client_id);
            $folder = $folders->clientSubfolder($client, 'Signed Agreement', $request->user()->id);
            $file = $files->uploadForClientFolder($request->file('file'), $client->id, $folder->id, $request->user()->id);
        } else {
            $file = $files->upload($request->file('file'), (int) $agreement->generatedFile?->folder_id, $request->user()->id);
        }

        $file->forceFill([
            'verified' => true,
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ])->save();

        $agreement->forceFill([
            'signed_file_id' => $file->id,
            'signed_at' => now(),
            'status' => 'signed',
        ])->save();

        return $this->created([
            'agreement' => new AgreementResource($agreement->refresh()->load(['generatedFile', 'signedFile'])),
            'file' => new \Modules\Files\Http\Resources\FileResource($file),
        ], 'Signed agreement uploaded and verified.');
    }
}
