<?php

namespace Modules\Checklists\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Checklists\Models\ChecklistCategory;

class ChecklistCategoriesController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $categories = ChecklistCategory::query()
            ->when($request->filled('owner'), fn ($q) => $q->where('owner', $request->string('owner')))
            ->when($request->filled('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('owner')
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn (ChecklistCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'owner' => $c->owner,
                'order' => $c->order,
                'is_active' => $c->is_active,
            ]);

        return $this->ok($categories);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);
        $category = ChecklistCategory::create([...$validated, 'created_by' => $request->user()->id]);

        return $this->created($this->present($category));
    }

    public function update(Request $request, ChecklistCategory $checklistCategory)
    {
        $validated = $this->validated($request, $checklistCategory->id);
        $checklistCategory->update($validated);

        return $this->ok($this->present($checklistCategory->refresh()), 'Category updated.');
    }

    public function destroy(ChecklistCategory $checklistCategory)
    {
        $checklistCategory->delete();

        return $this->noContent();
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('checklist_categories', 'name')
                    ->where(fn ($q) => $q->where('owner', $request->input('owner')))
                    ->ignore($ignoreId),
            ],
            'owner' => ['nullable', Rule::in(['applicant', 'inviter', 'internal'])],
            'order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function present(ChecklistCategory $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'owner' => $c->owner,
            'order' => $c->order,
            'is_active' => $c->is_active,
        ];
    }
}
