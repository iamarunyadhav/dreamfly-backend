<?php

namespace Modules\Checklists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Checklists\Http\Requests\StoreChecklistTemplateRequest;
use Modules\Checklists\Http\Requests\UpdateChecklistTemplateRequest;
use Modules\Checklists\Http\Resources\ChecklistTemplateResource;
use Modules\Checklists\Models\ChecklistTemplate;
use Modules\Checklists\Services\ChecklistTemplateService;

class ChecklistsController extends Controller
{
    use ApiResponse;

    public function __construct(protected ChecklistTemplateService $service)
    {
    }

    public function index(Request $request)
    {
        $checklistTemplates = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            filters: $request->only(['search', 'category', 'owner', 'status']),
        );

        return $this->ok(ChecklistTemplateResource::collection($checklistTemplates));
    }

    public function store(StoreChecklistTemplateRequest $request)
    {
        $checklistTemplate = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return $this->created(new ChecklistTemplateResource($checklistTemplate));
    }

    public function show(ChecklistTemplate $checklist)
    {
        return $this->ok(new ChecklistTemplateResource($checklist));
    }

    public function update(UpdateChecklistTemplateRequest $request, ChecklistTemplate $checklist)
    {
        $checklist = $this->service->update($checklist, $request->validated());

        return $this->ok(new ChecklistTemplateResource($checklist), 'Checklist template updated successfully.');
    }

    public function publish(Request $request, ChecklistTemplate $checklist)
    {
        $checklist = $this->service->publish($checklist, $request->user()->id);

        return $this->ok(new ChecklistTemplateResource($checklist->loadCount('versions')), 'Checklist item published.');
    }

    public function versions(ChecklistTemplate $checklist)
    {
        return $this->ok($checklist->versions()->with('publisher')->get()->map(fn ($v) => [
            'id' => $v->id,
            'version' => $v->version,
            'title' => $v->title,
            'owner' => $v->owner,
            'category' => $v->category,
            'is_required' => (bool) $v->is_required,
            'document_required' => (bool) $v->document_required,
            'published_by_name' => $v->publisher?->name,
            'published_at' => $v->published_at,
        ]));
    }

    public function restore(Request $request, ChecklistTemplate $checklist)
    {
        $validated = $request->validate(['version' => ['required', 'integer', 'min:1']]);

        $checklist = $this->service->restore($checklist, (int) $validated['version']);

        return $this->ok(new ChecklistTemplateResource($checklist->loadCount('versions')), 'Checklist item restored from version as a draft.');
    }

    public function destroy(ChecklistTemplate $checklist)
    {
        $this->service->delete($checklist);

        return $this->noContent();
    }
}
