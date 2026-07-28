<?php

namespace Modules\Agreements\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Modules\Agreements\Http\Requests\StoreAgreementRequest;
use Modules\Agreements\Http\Resources\AgreementResource;
use Modules\Agreements\Models\Agreement;
use Modules\Agreements\Services\AgreementDocumentService;
use Modules\Agreements\Services\AgreementPdfService;
use Modules\Agreements\Services\AgreementService;
use Modules\Communications\Http\Resources\MessageResource;
use Modules\Communications\Services\MessageService;
use Modules\Files\Services\FileService;

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
            with: ['generatedFile'],
            filters: $request->only(['search', 'status']),
        );

        return $this->ok(AgreementResource::collection($agreements));
    }

    public function store(StoreAgreementRequest $request)
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
        $validated = $request->validate([
            'folder_id' => ['required', 'integer', 'exists:folders,id'],
            'file_name' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $documents->generate($agreement, (int) $validated['folder_id'], $validated['file_name'] ?? null, $request->user()->id);

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

    public function share(Request $request, Agreement $agreement, AgreementDocumentService $documents, MessageService $messages, FileService $files)
    {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['whatsapp', 'email', 'sms'])],
            'recipient' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'welcome_message' => ['nullable', 'string'],
            'bank_instructions' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,mp4'],
        ]);

        // Make sure an unsigned agreement PDF exists to attach.
        $file = $agreement->generatedFile ?: $documents->generate($agreement, null, null, $request->user()->id);
        $agreementLink = URL::temporarySignedRoute('api.files.signed-download', now()->addDays(7), $file->id);

        $sections = [];
        $welcome = trim((string) ($validated['welcome_message'] ?? ''));
        $sections[] = $welcome !== '' ? $welcome : 'Welcome to Dream Fly Visa Consultancy. Please find your service agreement below.';

        $bank = trim((string) ($validated['bank_instructions'] ?? ''));
        if ($bank !== '') {
            $sections[] = "PAYMENT / BANK DETAILS:\n".$bank;
        }

        $sections[] = 'Unsigned Agreement: '.$agreementLink;

        if ($request->hasFile('attachment')) {
            $extra = $agreement->client_id
                ? $files->uploadForClientFolder($request->file('attachment'), (int) $agreement->client_id, (int) $file->folder_id, $request->user()->id)
                : $files->upload($request->file('attachment'), (int) $file->folder_id, $request->user()->id);
            $sections[] = 'Attachment: '.URL::temporarySignedRoute('api.files.signed-download', now()->addDays(7), $extra->id);
        }

        $message = $messages->send([
            'channel' => $validated['channel'],
            'recipient' => $validated['recipient'],
            'client_id' => $agreement->client_id,
            'workflow_step' => 'agreement',
            'subject' => $validated['subject'] ?? $agreement->reference_no.' Service Agreement',
            'body' => implode("\n\n", $sections),
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
}
