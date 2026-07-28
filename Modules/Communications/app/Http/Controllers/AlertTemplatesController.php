<?php

namespace Modules\Communications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Communications\Http\Requests\StoreAlertTemplateRequest;
use Modules\Communications\Http\Requests\UpdateAlertTemplateRequest;
use Modules\Communications\Http\Resources\AlertTemplateResource;
use Modules\Communications\Models\AlertTemplate;

class AlertTemplatesController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $alerts = AlertTemplate::query()
            ->with('messageTemplate')
            ->when($request->filled('trigger'), fn ($query) => $query->where('trigger', $request->string('trigger')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->where(fn ($nested) => $nested
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('trigger', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate((int) $request->integer('per_page', 20));

        return $this->ok(AlertTemplateResource::collection($alerts));
    }

    public function store(StoreAlertTemplateRequest $request)
    {
        $alert = AlertTemplate::create([
            ...$request->validated(),
            'delay_minutes' => $request->validated('delay_minutes') ?? 0,
            'is_enabled' => $request->validated('is_enabled') ?? true,
            'created_by' => $request->user()->id,
        ]);

        return $this->created(new AlertTemplateResource($alert->load('messageTemplate')));
    }

    public function update(UpdateAlertTemplateRequest $request, AlertTemplate $alert)
    {
        $alert->update($request->validated());

        return $this->ok(new AlertTemplateResource($alert->load('messageTemplate')));
    }

    public function destroy(AlertTemplate $alert)
    {
        $alert->delete();

        return $this->noContent();
    }
}
