<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\AuditLogs\Http\Resources\AuditLogResource;
use Modules\Clients\Http\Resources\ClientNoteResource;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Communications\Http\Resources\MessageResource;
use Modules\Communications\Models\Message;
use Modules\Files\Http\Resources\FileResource;
use Modules\Payments\Http\Resources\PaymentResource;
use Spatie\Activitylog\Models\Activity;

class ClientProfileController extends Controller
{
    use ApiResponse;

    private const STAGES = [
        'admin_summary' => ['label' => 'Admin Summary', 'owner' => 'Admin / Supervisor'],
        'application_unit' => ['label' => 'Application Unit', 'owner' => 'Application Unit Staff'],
        'documentation_unit' => ['label' => 'Documentation Unit', 'owner' => 'Documentation Unit Staff'],
        'supervisor_review' => ['label' => 'Supervisor Review', 'owner' => 'Supervisor'],
        'invoice' => ['label' => 'Invoice and Final Payment', 'owner' => 'Accounts Staff'],
        'submission' => ['label' => 'Submission', 'owner' => 'Documentation Unit Staff'],
        'visa_result' => ['label' => 'Visa Result', 'owner' => 'Supervisor'],
        'closed' => ['label' => 'Closed', 'owner' => 'Supervisor'],
    ];

    public function show(Request $request, Client $client)
    {
        $client->load(['adminSummary', 'applicationUnit', 'documentationTasks.assignedUser']);

        return $this->ok([
            'client' => new ClientResource($client),
            'documents' => FileResource::collection(
                $client->documents()->with('folder')->latest()->limit(100)->get()
            ),
            'payments' => PaymentResource::collection(
                $client->payments()->latest('paid_at')->latest('id')->get()
            ),
            'workflow' => $this->workflow($client),
            'communications' => MessageResource::collection($this->communications($client)),
            'notes' => ClientNoteResource::collection(
                $client->notes()->with('creator')->latest()->limit(50)->get()
            ),
            'audit_logs' => AuditLogResource::collection($this->auditLogs($client)),
        ]);
    }

    private function workflow(Client $client): array
    {
        $currentIndex = array_search($client->current_stage, array_keys(self::STAGES), true);
        $currentIndex = $currentIndex === false ? -1 : $currentIndex;

        return collect(self::STAGES)->map(function (array $stage, string $key) use ($client, $currentIndex) {
            $index = array_search($key, array_keys(self::STAGES), true);
            $status = $index < $currentIndex ? 'completed' : ($key === $client->current_stage ? 'current' : 'not_started');
            $startedAt = null;
            $completedAt = null;
            $generatedFileId = null;
            $notes = null;

            if ($key === 'admin_summary' && $client->adminSummary) {
                $startedAt = $client->adminSummary->started_at ?? $client->adminSummary->created_at;
                $completedAt = $client->adminSummary->completed_at;
                $generatedFileId = $client->adminSummary->generated_file_id;
                $notes = $client->adminSummary->summary;
                $status = $client->adminSummary->status === 'completed' ? 'completed' : $status;
            }

            if ($key === 'application_unit' && $client->applicationUnit) {
                $startedAt = $client->applicationUnit->started_at ?? $client->applicationUnit->created_at;
                $completedAt = $client->applicationUnit->completed_at;
                $generatedFileId = $client->applicationUnit->generated_file_id;
                $notes = $client->applicationUnit->notes;
                $status = $client->applicationUnit->status === 'completed' ? 'completed' : $status;
            }

            if ($key === 'documentation_unit') {
                $tasks = $client->documentationTasks;
                $notes = $tasks->count()
                    ? $tasks->where('status', 'completed')->count().' of '.$tasks->count().' documentation tasks completed.'
                    : null;
            }

            return [
                'key' => $key,
                'label' => $stage['label'],
                'owner' => $stage['owner'],
                'status' => $status,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'generated_file_id' => $generatedFileId,
                'notes' => $notes,
            ];
        })->values()->all();
    }

    private function communications(Client $client)
    {
        $recipients = collect([$client->phone, $client->email])->filter()->values()->all();

        return Message::query()
            ->where('client_id', $client->id)
            ->when($recipients !== [], fn ($query) => $query->orWhereIn('recipient', $recipients))
            ->latest()
            ->limit(50)
            ->get();
    }

    private function auditLogs(Client $client)
    {
        return Activity::query()
            ->where(function ($query) use ($client) {
                $query->where(function ($subject) use ($client) {
                    $subject->where('subject_type', Client::class)->where('subject_id', $client->id);
                })->orWhere(function ($subject) use ($client) {
                    $subject->whereIn('subject_type', [
                        \Modules\Clients\Models\ClientAdminSummary::class,
                        \Modules\Clients\Models\ClientApplicationUnit::class,
                        \Modules\Clients\Models\DocumentationTask::class,
                        \Modules\Clients\Models\ClientNote::class,
                    ])->whereJsonContains('properties->attributes->client_id', $client->id);
                });
            })
            ->latest()
            ->limit(80)
            ->get();
    }
}
