<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Clients\Http\Resources\DocumentationTaskResource;
use Modules\Clients\Models\DocumentationTask;

class TaskQueuesController extends Controller
{
    use ApiResponse;

    public function my(Request $request)
    {
        $user = $request->user();
        $roles = $user->getRoleNames()->all();

        $tasks = $this->baseQuery()
            ->where(function ($query) use ($user, $roles) {
                $query->where('assigned_user_id', $user->id)
                    ->orWhere(function ($nested) use ($roles) {
                        $nested->whereNull('assigned_user_id')
                            ->whereIn('assigned_role', $roles);
                    });
            })
            // Lets a "Start" notification link land someone straight on their
            // own tasks for one client, instead of every client at once.
            ->when($request->integer('client'), fn ($query, $clientId) => $query->where('client_id', $clientId))
            ->paginate((int) $request->integer('per_page', 20));

        return $this->ok(DocumentationTaskResource::collection($tasks));
    }

    public function pending(Request $request)
    {
        $tasks = $this->baseQuery()
            ->whereIn('status', ['pending', 'assigned', 'in_progress'])
            ->paginate((int) $request->integer('per_page', 20));

        return $this->ok(DocumentationTaskResource::collection($tasks));
    }

    public function overdue(Request $request)
    {
        $tasks = $this->baseQuery()
            ->where(function ($query) {
                $query->where('status', 'overdue')
                    ->orWhere(function ($nested) {
                        $nested->whereIn('status', ['pending', 'assigned', 'in_progress'])
                            ->whereNotNull('due_at')
                            ->where('due_at', '<', now());
                    });
            })
            ->paginate((int) $request->integer('per_page', 20));

        return $this->ok(DocumentationTaskResource::collection($tasks));
    }

    private function baseQuery()
    {
        return DocumentationTask::query()
            ->with(['client', 'assignedUser'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderByRaw("case priority when 'urgent' then 1 when 'high' then 2 when 'normal' then 3 else 4 end")
            ->orderBy('due_at')
            ->latest('id');
    }
}
