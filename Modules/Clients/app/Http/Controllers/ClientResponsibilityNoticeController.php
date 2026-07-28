<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Http\Requests\UpsertClientResponsibilityNoticeRequest;
use Modules\Clients\Http\Resources\ClientResponsibilityNoticeResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientResponsibilityNotice;
use Modules\Clients\Services\ResponsibilityNoticeDocumentService;
use Modules\Communications\Http\Resources\MessageResource;
use Modules\Communications\Services\MessageService;
use Modules\Files\Http\Resources\FileResource;

class ClientResponsibilityNoticeController extends Controller
{
    use ApiResponse;

    public function show(Client $client, ResponsibilityNoticeDocumentService $documents)
    {
        $notice = $client->responsibilityNotice;

        return $this->ok([
            'notice' => $notice ? new ClientResponsibilityNoticeResource($notice->load('acknowledgedByUser')) : null,
            // Surfaced so the operator can see exactly what the notice will list
            // before generating it.
            'documents' => $documents->documentsFor($client),
        ]);
    }

    public function saveDraft(UpsertClientResponsibilityNoticeRequest $request, Client $client)
    {
        $notice = DB::transaction(fn () => $this->upsert($client, [
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ], $request->user()->id));

        return $this->ok(
            new ClientResponsibilityNoticeResource($notice),
            'Responsibility Notice draft saved.',
        );
    }

    public function generate(Request $request, Client $client, ResponsibilityNoticeDocumentService $documents)
    {
        $notice = $this->upsert($client, ['updated_by' => $request->user()->id], $request->user()->id);

        $file = $documents->generate($client, $notice, $request->user()->id);

        return $this->created([
            'notice' => new ClientResponsibilityNoticeResource($notice->refresh()),
            'file' => new FileResource($file),
        ], 'Responsibility Notice generated and saved to the client folder.');
    }

    public function share(
        Request $request,
        Client $client,
        ResponsibilityNoticeDocumentService $documents,
        MessageService $messages,
    ) {
        $validated = $request->validate([
            'channel' => ['required', Rule::in(['whatsapp', 'email', 'sms'])],
            'recipient' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $notice = $this->upsert($client, ['updated_by' => $request->user()->id], $request->user()->id);

        // Make sure a generated PDF exists to attach before sharing.
        if (! $notice->generated_file_id) {
            $documents->generate($client, $notice, $request->user()->id);
            $notice->refresh();
        }

        $link = URL::temporarySignedRoute(
            'api.files.signed-download',
            now()->addDays(7),
            $notice->generated_file_id,
        );

        $intro = trim((string) ($validated['message'] ?? ''));
        $body = implode("\n\n", array_filter([
            $intro !== '' ? $intro : 'Dear '.$client->full_name.', please review and confirm the Client Document Responsibility Notice for your visa application.',
            'Responsibility Notice: '.$link,
            'Please reply to this message confirming that you have read and accepted the notice. We can only proceed with your application once we have your confirmation.',
        ]));

        $message = $messages->send([
            'channel' => $validated['channel'],
            'recipient' => $validated['recipient'],
            'client_id' => $client->id,
            'workflow_step' => 'responsibility_notice',
            'subject' => $validated['subject'] ?? $client->reference_no.' Client Document Responsibility Notice',
            'body' => $body,
        ], $request->user()->id);

        $notice->forceFill([
            'shared_at' => now(),
            'status' => $notice->acknowledged ? $notice->status : 'shared',
        ])->save();

        return $this->created([
            'notice' => new ClientResponsibilityNoticeResource($notice->refresh()),
            'message' => new MessageResource($message),
        ], 'Responsibility Notice shared and recorded.');
    }

    public function acknowledge(Request $request, Client $client)
    {
        $validated = $request->validate([
            'acknowledgement_method' => ['required', Rule::in(['whatsapp_reply', 'email_reply', 'signed_copy', 'verbal', 'other'])],
            'acknowledgement_note' => ['nullable', 'string', 'max:2000'],
            'acknowledged_at' => ['nullable', 'date'],
        ]);

        $notice = $client->responsibilityNotice;

        if (! $notice || ! $notice->generated_file_id) {
            throw ValidationException::withMessages([
                'notice' => ['Generate the Responsibility Notice before recording an acknowledgement.'],
            ]);
        }

        $notice->forceFill([
            'acknowledged' => true,
            'acknowledged_at' => $validated['acknowledged_at'] ?? now(),
            'acknowledged_by' => $request->user()->id,
            'acknowledgement_method' => $validated['acknowledgement_method'],
            'acknowledgement_note' => $validated['acknowledgement_note'] ?? null,
            'status' => 'acknowledged',
            'updated_by' => $request->user()->id,
        ])->save();

        return $this->ok(
            new ClientResponsibilityNoticeResource($notice->refresh()->load('acknowledgedByUser')),
            'Client acknowledgement recorded.',
        );
    }

    public function revokeAcknowledgement(Request $request, Client $client)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $notice = $client->responsibilityNotice;

        if (! $notice?->acknowledged) {
            throw ValidationException::withMessages([
                'notice' => ['This notice is not currently acknowledged.'],
            ]);
        }

        $notice->forceFill([
            'acknowledged' => false,
            'acknowledged_at' => null,
            'acknowledged_by' => null,
            'acknowledgement_method' => null,
            'acknowledgement_note' => $validated['reason'],
            'status' => $notice->shared_at ? 'shared' : 'generated',
            'updated_by' => $request->user()->id,
        ])->save();

        return $this->ok(
            new ClientResponsibilityNoticeResource($notice->refresh()),
            'Acknowledgement revoked.',
        );
    }

    /**
     * Fetch-or-start the client's notice. Status is only seeded on creation so a
     * later draft save never walks an acknowledged notice back to `draft`.
     */
    private function upsert(Client $client, array $attributes, int $userId): ClientResponsibilityNotice
    {
        $notice = ClientResponsibilityNotice::firstOrNew(['client_id' => $client->id]);

        if (! $notice->exists) {
            $notice->status = 'draft';
            $notice->created_by = $userId;
        }

        $notice->fill($attributes)->save();

        return $notice;
    }
}
