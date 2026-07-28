<?php

namespace Modules\Services\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Services\Http\Requests\StoreServiceRequest;
use Modules\Services\Http\Requests\UpdateServiceRequest;
use Modules\Services\Http\Resources\ServiceResource;
use Modules\Services\Models\Service;

class ServicesController extends Controller
{
    use ApiResponse;

    private const RELATIONS = ['workflowTemplate', 'checklistTemplates', 'forms', 'messageTemplates'];

    public function index(Request $request)
    {
        $query = Service::query()
            ->withCount(['checklistTemplates', 'forms', 'messageTemplates'])
            ->with('workflowTemplate');

        if ($search = $request->string('search')->trim()->value()) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($category = $request->string('category')->trim()->value()) {
            $query->where('category', $category);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $this->ok(ServiceResource::collection($query->latest()->paginate((int) $request->integer('per_page', 20))));
    }

    public function store(StoreServiceRequest $request)
    {
        $service = DB::transaction(function () use ($request) {
            $service = Service::create([
                ...collect($request->validated())->only(['name', 'category', 'description', 'workflow_template_id', 'is_active'])->all(),
                'created_by' => $request->user()->id,
            ]);
            $this->syncRelations($service, $request->validated());

            return $service;
        });

        return $this->created(new ServiceResource($service->load(self::RELATIONS)));
    }

    public function show(Service $service)
    {
        return $this->ok(new ServiceResource($service->load(self::RELATIONS)));
    }

    public function update(UpdateServiceRequest $request, Service $service)
    {
        DB::transaction(function () use ($request, $service) {
            $service->update(
                collect($request->validated())->only(['name', 'category', 'description', 'workflow_template_id', 'is_active'])->all()
            );
            $this->syncRelations($service, $request->validated());
        });

        return $this->ok(new ServiceResource($service->load(self::RELATIONS)), 'Service updated successfully.');
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return $this->noContent();
    }

    private function syncRelations(Service $service, array $data): void
    {
        if (array_key_exists('checklist_templates', $data)) {
            $pivot = collect($data['checklist_templates'])->mapWithKeys(fn (array $item, int $index) => [
                $item['id'] => [
                    'is_required' => $item['is_required'] ?? true,
                    'order' => $item['order'] ?? $index,
                ],
            ])->all();
            $service->checklistTemplates()->sync($pivot);
        }

        if (array_key_exists('form_ids', $data)) {
            $service->forms()->sync($data['form_ids']);
        }

        if (array_key_exists('message_templates', $data)) {
            $pivot = collect($data['message_templates'])->mapWithKeys(fn (array $item) => [
                $item['id'] => ['purpose' => $item['purpose'] ?? null],
            ])->all();
            $service->messageTemplates()->sync($pivot);
        }
    }
}
