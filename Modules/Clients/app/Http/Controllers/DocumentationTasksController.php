<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Clients\Http\Requests\StoreDocumentationTaskRequest;
use Modules\Clients\Http\Requests\UpdateDocumentationTaskRequest;
use Modules\Clients\Http\Resources\DocumentationTaskResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\DocumentationTask;
use Modules\Clients\Services\DocumentationTaskAssignmentNotifier;
use Modules\Clients\Services\DocumentationTaskBlockerNotifier;
use Modules\Files\Services\FileService;
use Modules\Folders\Services\FolderService;

class DocumentationTasksController extends Controller
{
    use ApiResponse;

    public function index(Client $client)
    {
        return $this->ok(DocumentationTaskResource::collection(
            $client->documentationTasks()
                ->with(['assignedUser', 'file'])
                ->orderByRaw("case status when 'completed' then 1 else 0 end")
                ->orderBy('due_at')
                ->latest('id')
                ->get()
        ));
    }

    public function store(StoreDocumentationTaskRequest $request, Client $client)
    {
        $task = DB::transaction(function () use ($request, $client) {
            return DocumentationTask::create([
                ...$this->timestampsForStatus($request->validated()),
                'client_id' => $client->id,
                'status' => $request->validated('status', 'pending'),
                'priority' => $request->validated('priority', 'normal'),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });

        return $this->created(new DocumentationTaskResource($task->load('assignedUser')), 'Documentation task created.');
    }

    public function update(UpdateDocumentationTaskRequest $request, Client $client, DocumentationTask $task, DocumentationTaskBlockerNotifier $blockerNotifier)
    {
        if ($task->client_id !== $client->id) {
            abort(404);
        }

        $wasOnHold = $task->status === 'on_hold';

        $task = DB::transaction(function () use ($request, $task) {
            $task->forceFill([
                ...$this->timestampsForStatus($request->validated(), $task),
                'updated_by' => $request->user()->id,
            ])->save();

            return $task;
        });

        // Only the transition into on_hold raises the alert - re-saving an
        // already-blocked task (e.g. editing its note) does not re-notify.
        if ($task->status === 'on_hold' && ! $wasOnHold) {
            $blockerNotifier->notifyBlocked($client, $task, $request->user()->id);
        }

        return $this->ok(new DocumentationTaskResource($task->load('assignedUser')), 'Documentation task updated.');
    }

    public function destroy(Request $request, Client $client, DocumentationTask $task)
    {
        if ($task->client_id !== $client->id) {
            abort(404);
        }

        $task->forceFill(['updated_by' => $request->user()->id])->save();
        $task->delete();

        return $this->noContent();
    }

    /**
     * Attach (or replace) the working file for one task. Independent of
     * status - uploading a document does not itself start/complete the task.
     */
    public function uploadFile(Request $request, Client $client, DocumentationTask $task, FileService $files, FolderService $folders)
    {
        if ($task->client_id !== $client->id) {
            abort(404);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,pdf,mp4,docx'],
        ]);

        return DB::transaction(function () use ($request, $client, $task, $files, $folders) {
            $uploadedFile = $request->file('file');
            $file = $task->file
                ? $files->uploadNewVersion(
                    current: $task->file,
                    uploadedFile: $uploadedFile,
                    uploadedBy: $request->user()->id,
                    note: 'Replaced from the Correction Unit task "'.$task->title.'".',
                )
                : $files->uploadForClientFolder(
                    uploadedFile: $uploadedFile,
                    clientId: $client->id,
                    folderId: $folders->clientSubfolder($client, 'Correction Unit', $request->user()->id)->id,
                    uploadedBy: $request->user()->id,
                );

            $task->forceFill(['file_id' => $file->id, 'updated_by' => $request->user()->id])->save();

            return $this->ok(new DocumentationTaskResource($task->refresh()->load('file')), 'Document attached to task.');
        });
    }

    /**
     * Notify every staff member with a Correction Unit task on this client
     * (plus every Admin/Super Admin) that task assignments are confirmed, ahead
     * of marking the documentation_unit case step complete.
     */
    public function confirmAssignments(Request $request, Client $client, DocumentationTaskAssignmentNotifier $notifier)
    {
        $summary = $notifier->notifyAssignmentsConfirmed($client, $request->user()->id);

        return $this->ok($summary, 'Assignment notifications sent.');
    }

    private function timestampsForStatus(array $data, ?DocumentationTask $existing = null): array
    {
        $status = $data['status'] ?? $existing?->status ?? 'pending';

        if ($status === 'completed') {
            $data['completed_at'] = $data['completed_at'] ?? $existing?->completed_at ?? now();
        } elseif (array_key_exists('status', $data)) {
            $data['completed_at'] = null;
        }

        return $data;
    }
}
