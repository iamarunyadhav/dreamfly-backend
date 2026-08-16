<?php

namespace Modules\Clients\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Http\Resources\ClientResource;
use Modules\Clients\Models\Client;
use Modules\Clients\Services\DocumentPrepSummaryDocumentService;
use Modules\Clients\Services\StepHandoffNotifier;
use Modules\Files\Http\Resources\FileResource;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Services\CaseStepService;

class ClientDocumentPrepUnitController extends Controller
{
    use ApiResponse;

    public function generateSummary(Request $request, Client $client, DocumentPrepSummaryDocumentService $documentService)
    {
        $file = $documentService->generate($client, $request->user()->id);

        return $this->created(new FileResource($file), 'Documentation Unit summary generated and saved.');
    }

    /**
     * "Complete and assign" for the Documentation Unit stage - mirrors
     * ClientApplicationUnitController::complete()'s handoff pattern exactly,
     * so whichever staff member is chosen next is emailed/WhatsApp'd directly
     * (in addition to the generic stage_assigned alert advance() already fires).
     */
    public function complete(Request $request, Client $client, CaseStepService $caseSteps, StepHandoffNotifier $notifier)
    {
        if ($client->current_stage !== 'document_prep_unit') {
            throw ValidationException::withMessages([
                'current_stage' => ['Documentation Unit can only be completed while the case is in the Documentation Unit stage.'],
            ]);
        }

        $validated = $request->validate([
            'next_assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $nextAssignedUserId = $validated['next_assigned_user_id'] ?? $client->assigned_supervisor_id;

        return DB::transaction(function () use ($request, $client, $caseSteps, $notifier, $nextAssignedUserId) {
            $step = CaseStep::where('client_id', $client->id)->where('key', 'document_prep_unit')->first();
            if (! $step) {
                throw ValidationException::withMessages([
                    'current_stage' => ['This case has no Documentation Unit step to complete.'],
                ]);
            }

            $nextStep = CaseStep::where('client_id', $client->id)
                ->where('order', '>', $step->order)
                ->whereNotIn('status', ['completed', 'skipped'])
                ->orderBy('order')
                ->first();

            $caseSteps->advance($step, $request->user()->id, null, $nextAssignedUserId);

            $assignee = $nextAssignedUserId ? User::find($nextAssignedUserId) : null;
            $handoffResult = ($assignee && $nextStep)
                ? $notifier->notifyHandoff($client, $assignee, 'Documentation Unit', $nextStep->name, null, $request->user()->id, 'document_prep_unit')
                : null;

            return $this->ok([
                'client' => new ClientResource($client->refresh()),
                'handoff' => $handoffResult,
            ], 'Documentation Unit completed and handed off.');
        });
    }
}
