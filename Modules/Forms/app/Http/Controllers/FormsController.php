<?php

namespace Modules\Forms\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Forms\Http\Requests\StoreFormRequest;
use Modules\Forms\Http\Requests\UpdateFormRequest;
use Modules\Forms\Http\Resources\FormResource;
use Modules\Forms\Models\Form;
use Modules\Forms\Services\FormService;

class FormsController extends Controller
{
    use ApiResponse;

    public function __construct(protected FormService $service)
    {
    }

    public function index(Request $request)
    {
        $forms = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            with: ['fields'],
            filters: $request->only(['search', 'status']),
        );

        return $this->ok(FormResource::collection($forms));
    }

    public function store(StoreFormRequest $request)
    {
        $form = $this->service->create([...$request->validated(), 'created_by' => $request->user()->id]);

        return $this->created(new FormResource($form->load('fields')));
    }

    public function show(Form $form)
    {
        return $this->ok(new FormResource($form->load('fields')));
    }

    public function update(UpdateFormRequest $request, Form $form)
    {
        $form = $this->service->update($form, $request->validated());

        return $this->ok(new FormResource($form->load('fields')), 'Form updated successfully.');
    }

    public function destroy(Form $form)
    {
        $this->service->delete($form);

        return $this->noContent();
    }
}
