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

class DocumentationTasksController extends Controller
{
    use ApiResponse;

    public function index(Client $client)
    {
        return $this->ok(DocumentationTaskResource::collection(
            $client->documentationTasks()
                ->with('assignedUser')
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

    public function update(UpdateDocumentationTaskRequest $request, Client $client, DocumentationTask $task)
    {
        if ($task->client_id !== $client->id) {
            abort(404);
        }

        $task = DB::transaction(function () use ($request, $task) {
            $task->forceFill([
                ...$this->timestampsForStatus($request->validated(), $task),
                'updated_by' => $request->user()->id,
            ])->save();

            return $task;
        });

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
