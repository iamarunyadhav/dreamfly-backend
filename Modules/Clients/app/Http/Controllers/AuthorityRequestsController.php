<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Clients\Http\Resources\AuthorityRequestResource;
use Modules\Clients\Models\AuthorityRequest;
use Modules\Clients\Models\Client;
use Modules\Files\Services\FileService;
use Modules\Folders\Services\FolderService;

class AuthorityRequestsController extends Controller
{
    use ApiResponse;

    private const TYPES = ['additional_documents', 'interview', 'medical', 'biometrics', 'clarification', 'other'];

    private const STATUSES = ['pending', 'in_progress', 'responded', 'overdue', 'closed', 'cancelled'];

    public function index(Client $client)
    {
        $requests = AuthorityRequest::with(['assignedUser', 'responseFile'])
            ->where('client_id', $client->id)
            ->orderByRaw('CASE WHEN status IN ("responded","closed","cancelled") THEN 1 ELSE 0 END')
            ->orderByRaw('due_at IS NULL')
            ->orderBy('due_at')
            ->orderByDesc('received_at')
            ->get();

        return $this->ok(AuthorityRequestResource::collection($requests));
    }

    public function store(Request $request, Client $client)
    {
        $validated = $request->validate([
            'authority' => ['required', 'string', 'max:255'],
            'request_type' => ['required', Rule::in(self::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'received_at' => ['required', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:received_at'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $authorityRequest = AuthorityRequest::create([
            ...$validated,
            'client_id' => $client->id,
            'status' => 'pending',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->created(
            new AuthorityRequestResource($authorityRequest->load('assignedUser')),
            'Authority request logged.',
        );
    }

    public function update(Request $request, Client $client, AuthorityRequest $authorityRequest)
    {
        abort_unless($authorityRequest->client_id === $client->id, 404);

        $validated = $request->validate([
            'authority' => ['sometimes', 'string', 'max:255'],
            'request_type' => ['sometimes', Rule::in(self::TYPES)],
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'received_at' => ['sometimes', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'responded_at' => ['sometimes', 'nullable', 'date'],
            'response_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        // Marking a request responded stamps the date automatically when the
        // operator did not supply one.
        if (($validated['status'] ?? null) === 'responded' && empty($validated['responded_at']) && ! $authorityRequest->responded_at) {
            $validated['responded_at'] = now()->toDateString();
        }

        $authorityRequest->fill([...$validated, 'updated_by' => $request->user()->id])->save();

        return $this->ok(
            new AuthorityRequestResource($authorityRequest->refresh()->load(['assignedUser', 'responseFile'])),
            'Authority request updated.',
        );
    }

    /** Attach the document sent back to the authority. */
    public function uploadResponse(Request $request, Client $client, AuthorityRequest $authorityRequest, FileService $files, FolderService $folders)
    {
        abort_unless($authorityRequest->client_id === $client->id, 404);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx'],
        ]);

        $folder = $folders->clientSubfolder($client, 'Final Documents', $request->user()->id);
        $file = $files->uploadForClientFolder($request->file('file'), $client->id, $folder->id, $request->user()->id);

        $authorityRequest->forceFill([
            'response_file_id' => $file->id,
            'updated_by' => $request->user()->id,
        ])->save();

        return $this->created(
            new AuthorityRequestResource($authorityRequest->refresh()->load(['assignedUser', 'responseFile'])),
            'Response document attached.',
        );
    }

    public function destroy(Client $client, AuthorityRequest $authorityRequest)
    {
        abort_unless($authorityRequest->client_id === $client->id, 404);

        $authorityRequest->delete();

        return $this->noContent('Authority request deleted.');
    }
}
