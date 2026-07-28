<?php

namespace Modules\Workflows\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Workflows\Http\Requests\StoreWorkflowTemplateRequest;
use Modules\Workflows\Http\Requests\UpdateWorkflowTemplateRequest;
use Modules\Workflows\Http\Resources\WorkflowTemplateResource;
use Modules\Workflows\Models\WorkflowTemplate;
use Modules\Workflows\Services\WorkflowTemplateService;

class WorkflowsController extends Controller
{
    use ApiResponse;

    public function __construct(protected WorkflowTemplateService $service)
    {
    }

    public function index(Request $request)
    {
        $workflowTemplates = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            with: ['steps'],
            filters: $request->only(['search', 'service_type', 'is_active']),
        );

        return $this->ok(WorkflowTemplateResource::collection($workflowTemplates));
    }

    public function store(StoreWorkflowTemplateRequest $request)
    {
        $workflowTemplate = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return $this->created(new WorkflowTemplateResource($workflowTemplate->load('steps')));
    }

    public function show(WorkflowTemplate $workflow)
    {
        $workflow = $this->service->find($workflow->id, ['steps']);

        return $this->ok(new WorkflowTemplateResource($workflow));
    }

    public function update(UpdateWorkflowTemplateRequest $request, WorkflowTemplate $workflow)
    {
        $workflow = $this->service->update($workflow, $request->validated());

        return $this->ok(new WorkflowTemplateResource($workflow->load('steps')), 'Workflow template updated successfully.');
    }

    public function destroy(WorkflowTemplate $workflow)
    {
        $this->service->delete($workflow);

        return $this->noContent();
    }
}
