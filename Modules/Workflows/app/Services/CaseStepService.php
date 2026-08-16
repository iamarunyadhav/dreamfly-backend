<?php

namespace Modules\Workflows\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Checklists\Models\CaseChecklistItem;
use Modules\Clients\Models\Client;
use Modules\Clients\Models\ClientCaseClosure;
use Modules\Clients\Models\ClientResponsibilityNotice;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Models\WorkflowTemplate;

class CaseStepService
{
    public function __construct(private \Modules\Communications\Services\AlertDispatcher $alerts)
    {
    }

    /**
     * The default case journey, used when no workflow template is supplied. Keys
     * mirror the client's `current_stage` enum so the runtime and the existing
     * stage field stay in lock-step.
     */
    private const DEFAULT_STAGES = [
        ['key' => 'admin_summary', 'name' => 'Admin Summary', 'owner_role' => 'Supervisor', 'duration_days' => 2, 'requires_checklist' => false],
        ['key' => 'application_unit', 'name' => 'Application Unit', 'owner_role' => 'Application Unit Staff', 'duration_days' => 5, 'requires_checklist' => false],
        // 'documentation_unit' is the key's original name; the stage itself
        // is labeled/owned as Correction Unit since the 2026-08-08 rename.
        ['key' => 'documentation_unit', 'name' => 'Correction Unit', 'owner_role' => 'Correction Unit Staff', 'duration_days' => 7, 'requires_checklist' => true],
        // Genuinely new stage - the office/paperwork team that picks up after
        // Correction Unit finishes verifying documents.
        ['key' => 'document_prep_unit', 'name' => 'Documentation Unit', 'owner_role' => 'Documentation Unit Staff', 'duration_days' => 5, 'requires_checklist' => false],
        // Genuinely new stage - gathers, compresses, and hands off the case's
        // documents once Documentation Unit's own work is done.
        ['key' => 'upload_team', 'name' => 'Upload Team', 'owner_role' => 'Upload Team Staff', 'duration_days' => 2, 'requires_checklist' => false],
        ['key' => 'supervisor_review', 'name' => 'Supervisor Review', 'owner_role' => 'Supervisor', 'duration_days' => 2, 'requires_checklist' => false],
        ['key' => 'responsibility_notice', 'name' => 'Responsibility Notice', 'owner_role' => 'Correction Unit Staff', 'duration_days' => 2, 'requires_checklist' => false, 'requires_acknowledgement' => true],
        ['key' => 'invoice', 'name' => 'Invoice and Final Payment', 'owner_role' => 'Accounts Staff', 'duration_days' => 3, 'requires_checklist' => false],
        ['key' => 'submission', 'name' => 'Submission', 'owner_role' => 'Correction Unit Staff', 'duration_days' => 3, 'requires_checklist' => true],
        ['key' => 'visa_result', 'name' => 'Visa Result', 'owner_role' => 'Supervisor', 'duration_days' => 30, 'requires_checklist' => false],
        ['key' => 'closed', 'name' => 'Closed', 'owner_role' => 'Supervisor', 'duration_days' => null, 'requires_checklist' => false, 'requires_closure_record' => true],
    ];

    /**
     * Build the runtime case steps for a client from a workflow template (or the
     * default journey). Idempotent unless $force is set. Steps up to the client's
     * current stage are marked completed, the current one in progress.
     *
     * @return Collection<int, CaseStep>
     */
    public function initializeForClient(Client $client, ?WorkflowTemplate $template = null, bool $force = false): Collection
    {
        return DB::transaction(function () use ($client, $template, $force) {
            $existing = CaseStep::where('client_id', $client->id)->orderBy('order')->get();
            if ($existing->isNotEmpty() && ! $force) {
                return $existing;
            }

            if ($force) {
                CaseStep::where('client_id', $client->id)->delete();
            }

            // Configurable workflow per service: when no template is given, use
            // the one configured for the client's service category (if any).
            $template ??= $this->templateForClientService($client);

            $definitions = $this->stepDefinitions($template);
            $currentKey = $client->current_stage ?: ($definitions[0]['key'] ?? null);
            $currentIndex = collect($definitions)->search(fn (array $d) => $d['key'] === $currentKey);
            $currentIndex = $currentIndex === false ? 0 : $currentIndex;

            $steps = collect($definitions)->map(function (array $def, int $index) use ($client, $template, $currentIndex) {
                $status = $index < $currentIndex ? 'completed' : ($index === $currentIndex ? 'in_progress' : 'pending');

                return CaseStep::create([
                    'client_id' => $client->id,
                    'workflow_template_id' => $template?->id,
                    'workflow_step_id' => $def['workflow_step_id'] ?? null,
                    'key' => $def['key'],
                    'name' => $def['name'],
                    'order' => $index,
                    'owner_role' => $def['owner_role'] ?? null,
                    'status' => $status,
                    'duration_days' => $def['duration_days'] ?? null,
                    'requires_checklist' => (bool) ($def['requires_checklist'] ?? false),
                    'requires_acknowledgement' => (bool) ($def['requires_acknowledgement'] ?? false),
                    'requires_closure_record' => (bool) ($def['requires_closure_record'] ?? false),
                    'started_at' => $index <= $currentIndex ? now() : null,
                    'due_at' => $index === $currentIndex && ! empty($def['duration_days']) ? now()->addDays((int) $def['duration_days']) : null,
                    'completed_at' => $index < $currentIndex ? now() : null,
                ]);
            });

            return $steps->values();
        });
    }

    public function advance(CaseStep $step, int $userId, ?string $notes = null, ?int $nextAssignedUserId = null): CaseStep
    {
        return DB::transaction(function () use ($step, $userId, $notes, $nextAssignedUserId) {
            if (in_array($step->status, ['completed', 'skipped'], true)) {
                throw ValidationException::withMessages(['status' => 'This step is already completed.']);
            }

            // Transition validation: every earlier step must be done first.
            $earlierIncomplete = CaseStep::where('client_id', $step->client_id)
                ->where('order', '<', $step->order)
                ->whereNotIn('status', ['completed', 'skipped'])
                ->exists();
            if ($earlierIncomplete) {
                throw ValidationException::withMessages(['status' => 'Complete the earlier steps before this one.']);
            }

            // Completion rule: required checklist items must be resolved first.
            if ($step->requires_checklist && ($blocking = $this->blockingChecklistCount($step->client_id)) > 0) {
                throw ValidationException::withMessages([
                    'checklist' => "Cannot complete this step while {$blocking} required checklist item(s) are still outstanding.",
                ]);
            }

            // Completion rule: the client must have acknowledged the Responsibility
            // Notice before a gated step can be closed.
            if ($step->requires_acknowledgement && ! $this->noticeAcknowledged($step->client_id)) {
                throw ValidationException::withMessages([
                    'acknowledgement' => 'Cannot complete this step until the client has acknowledged the Responsibility Notice.',
                ]);
            }

            // Completion rule: the final-document handover must be recorded and
            // archived before the case can actually close.
            if ($step->requires_closure_record && ! $this->closureRecorded($step->client_id)) {
                throw ValidationException::withMessages([
                    'closure' => 'Cannot close this case until the final-document handover is recorded, archived, and completed.',
                ]);
            }

            $step->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'completed_by' => $userId,
                'notes' => $notes ?? $step->notes,
            ])->save();

            $next = CaseStep::where('client_id', $step->client_id)
                ->where('order', '>', $step->order)
                ->whereNotIn('status', ['completed', 'skipped'])
                ->orderBy('order')
                ->first();

            $client = Client::find($step->client_id);

            $base = [
                'client_id' => $step->client_id,
                'client_reference' => $client?->reference_no,
                'client_name' => $client?->full_name,
                'service_category' => $client?->service_category,
            ];

            $this->alerts->trigger('stage_completed', [
                ...$base,
                'stage_key' => $step->key,
                'stage_name' => $step->name,
                'owner_role' => $step->owner_role,
            ], "step-{$step->id}-completed");

            if ($next) {
                $next->forceFill([
                    'status' => 'in_progress',
                    'started_at' => $next->started_at ?? now(),
                    'due_at' => $next->duration_days ? now()->addDays((int) $next->duration_days) : null,
                    'assigned_user_id' => $nextAssignedUserId ?? $next->assigned_user_id,
                ])->save();

                $client?->forceFill(['current_stage' => $next->key])->save();

                $this->alerts->trigger('stage_assigned', [
                    ...$base,
                    'stage_key' => $next->key,
                    'stage_name' => $next->name,
                    'owner_role' => $next->owner_role,
                    'due_at' => $next->due_at?->format('d M Y H:i'),
                ], "step-{$next->id}-assigned");
            } else {
                // No further steps - the case is complete.
                $client?->forceFill(['current_stage' => 'closed', 'status' => 'closed'])->save();

                $this->alerts->trigger('case_closed', $base, "client-{$step->client_id}-closed");
            }

            return $step->refresh();
        });
    }

    /**
     * Push a case back to an earlier step - used when a supervisor rejects the
     * work and returns it for correction. The target step reopens, every step
     * after it resets to pending, and the client's stage follows.
     *
     * @return Collection<int, CaseStep>
     */
    public function sendBackTo(Client $client, string $stepKey, ?string $reason = null): Collection
    {
        return DB::transaction(function () use ($client, $stepKey, $reason) {
            $target = CaseStep::where('client_id', $client->id)->where('key', $stepKey)->first();

            if (! $target) {
                throw ValidationException::withMessages([
                    'step' => "This case has no \"{$stepKey}\" step to send back to.",
                ]);
            }

            $target->forceFill([
                'status' => 'in_progress',
                'started_at' => now(),
                'due_at' => $target->duration_days ? now()->addDays((int) $target->duration_days) : null,
                'completed_at' => null,
                'completed_by' => null,
                'hold_reason' => null,
                'held_at' => null,
                'notes' => $reason ?? $target->notes,
            ])->save();

            CaseStep::where('client_id', $client->id)
                ->where('order', '>', $target->order)
                ->update([
                    'status' => 'pending',
                    'started_at' => null,
                    'due_at' => null,
                    'completed_at' => null,
                    'completed_by' => null,
                ]);

            $client->forceFill(['current_stage' => $target->key, 'status' => 'active'])->save();

            return CaseStep::where('client_id', $client->id)->orderBy('order')->get();
        });
    }

    public function hold(CaseStep $step, string $reason): CaseStep
    {
        if (! in_array($step->status, ['in_progress', 'waiting', 'pending'], true)) {
            throw ValidationException::withMessages(['status' => 'Only an active step can be put on hold.']);
        }

        $step->forceFill([
            'status' => 'on_hold',
            'hold_reason' => $reason,
            'held_at' => now(),
        ])->save();

        return $step->refresh();
    }

    public function resume(CaseStep $step): CaseStep
    {
        if ($step->status !== 'on_hold') {
            throw ValidationException::withMessages(['status' => 'Only a step on hold can be resumed.']);
        }

        // SLA pause: push the due date out by however long the hold lasted so the
        // waiting time is not counted against the step's deadline.
        $dueAt = $step->due_at;
        if ($dueAt && $step->held_at) {
            $dueAt = $dueAt->clone()->addSeconds($step->held_at->diffInSeconds(now()));
        }

        $step->forceFill([
            'status' => 'in_progress',
            'due_at' => $dueAt,
            'hold_reason' => null,
            'held_at' => null,
        ])->save();

        return $step->refresh();
    }

    public function updateStep(CaseStep $step, array $attributes): CaseStep
    {
        $step->fill(array_filter(
            $attributes,
            fn ($value, $key) => in_array($key, ['owner_role', 'notes', 'due_at', 'duration_days'], true),
            ARRAY_FILTER_USE_BOTH
        ))->save();

        return $step->refresh();
    }

    /**
     * Number of required checklist items for the client that are not yet
     * completed or verified - these block a checklist-gated step.
     */
    public function blockingChecklistCount(int $clientId): int
    {
        return CaseChecklistItem::where('client_id', $clientId)
            ->where('is_required', true)
            ->whereNotIn('status', ['completed', 'verified'])
            ->count();
    }

    /**
     * Whether the client's Responsibility Notice has been acknowledged - the
     * gate for any step flagged `requires_acknowledgement`.
     */
    public function noticeAcknowledged(int $clientId): bool
    {
        return ClientResponsibilityNotice::where('client_id', $clientId)
            ->where('acknowledged', true)
            ->exists();
    }

    /**
     * Whether the client's final-document handover has been recorded, archived,
     * and signed off - the gate for any step flagged `requires_closure_record`.
     */
    public function closureRecorded(int $clientId): bool
    {
        return ClientCaseClosure::where('client_id', $clientId)
            ->where('archived', true)
            ->whereNotNull('completed_at')
            ->exists();
    }

    /**
     * Resolve the workflow template configured for the client's service
     * category, if an active service defines one.
     */
    private function templateForClientService(Client $client): ?WorkflowTemplate
    {
        if (! $client->service_category || ! class_exists(\Modules\Services\Models\Service::class)) {
            return null;
        }

        $service = \Modules\Services\Models\Service::query()
            ->where('category', $client->service_category)
            ->where('is_active', true)
            ->whereNotNull('workflow_template_id')
            ->latest()
            ->first();

        return $service?->workflowTemplate()->with('steps')->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stepDefinitions(?WorkflowTemplate $template): array
    {
        if ($template) {
            $template->loadMissing('steps');
            if ($template->steps->isNotEmpty()) {
                return $template->steps
                    ->sortBy('order')
                    ->values()
                    ->map(fn ($step, $index) => [
                        'key' => $step->key ?: 'step_'.($index + 1),
                        'name' => $step->name,
                        'owner_role' => $step->owner_role,
                        'duration_days' => $step->duration_days,
                        'requires_checklist' => (bool) $step->requires_checklist,
                        'requires_acknowledgement' => (bool) $step->requires_acknowledgement,
                        'requires_closure_record' => (bool) $step->requires_closure_record,
                        'workflow_step_id' => $step->id,
                    ])
                    ->all();
            }
        }

        return self::DEFAULT_STAGES;
    }
}
