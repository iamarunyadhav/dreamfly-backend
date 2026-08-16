<?php

namespace Modules\Workflows\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Clients\Models\Client;
use Modules\Workflows\Http\Resources\CaseStepResource;
use Modules\Workflows\Models\CaseStep;
use Modules\Workflows\Models\WorkflowTemplate;
use Modules\Workflows\Services\CaseStepService;

class CaseStepsController extends Controller
{
    use ApiResponse;

    public function __construct(protected CaseStepService $service)
    {
    }

    public function index(Client $client)
    {
        $steps = CaseStep::with(['completer', 'assignedUser'])->where('client_id', $client->id)->orderBy('order')->get();

        return $this->ok([
            'steps' => CaseStepResource::collection($steps),
            'blocking_checklist_count' => $this->service->blockingChecklistCount($client->id),
            'notice_acknowledged' => $this->service->noticeAcknowledged($client->id),
        ]);
    }

    public function initialize(Request $request, Client $client)
    {
        $validated = $request->validate([
            'workflow_template_id' => ['nullable', 'integer', 'exists:workflow_templates,id'],
            'force' => ['sometimes', 'boolean'],
        ]);

        $template = ! empty($validated['workflow_template_id'])
            ? WorkflowTemplate::with('steps')->find($validated['workflow_template_id'])
            : null;

        $steps = $this->service->initializeForClient($client, $template, (bool) ($validated['force'] ?? false));

        return $this->created([
            'steps' => CaseStepResource::collection($steps),
            'blocking_checklist_count' => $this->service->blockingChecklistCount($client->id),
            'notice_acknowledged' => $this->service->noticeAcknowledged($client->id),
        ], 'Case workflow initialized.');
    }

    public function advance(Request $request, CaseStep $caseStep)
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);

        $caseStep = $this->service->advance($caseStep, $request->user()->id, $validated['notes'] ?? null);

        return $this->ok(new CaseStepResource($caseStep->load(['completer', 'assignedUser'])), 'Step completed and case advanced.');
    }

    public function hold(Request $request, CaseStep $caseStep)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        $caseStep = $this->service->hold($caseStep, $validated['reason']);

        return $this->ok(new CaseStepResource($caseStep), 'Step placed on hold.');
    }

    public function resume(CaseStep $caseStep)
    {
        $caseStep = $this->service->resume($caseStep);

        return $this->ok(new CaseStepResource($caseStep), 'Step resumed.');
    }

    /**
     * Admin/Super-Admin-only: reopen an already-completed step. Cascades -
     * every later step resets to pending too, matching sendBackTo()'s existing
     * supervisor-rejection behavior exactly (nothing new inside the service).
     */
    public function reset(Request $request, CaseStep $caseStep)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        if ($caseStep->status !== 'completed') {
            throw ValidationException::withMessages([
                'status' => ['Only a completed step can be reopened.'],
            ]);
        }

        $client = Client::findOrFail($caseStep->client_id);
        $steps = $this->service->sendBackTo($client, $caseStep->key, $validated['reason']);

        return $this->ok(['steps' => CaseStepResource::collection($steps)], 'Step reopened; later steps reset to pending.');
    }

    public function update(Request $request, CaseStep $caseStep)
    {
        $validated = $request->validate([
            'owner_role' => ['sometimes', 'nullable', 'string', 'max:100'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'duration_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ]);

        $caseStep = $this->service->updateStep($caseStep, $validated);

        return $this->ok(new CaseStepResource($caseStep), 'Step updated.');
    }
}
