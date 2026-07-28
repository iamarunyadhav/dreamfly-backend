<?php

namespace Modules\Checklists\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Checklists\Models\ChecklistTemplate;
use Modules\Checklists\Repositories\Contracts\ChecklistTemplateRepositoryInterface;

class ChecklistTemplateRepository extends BaseRepository implements ChecklistTemplateRepositoryInterface
{
    public function __construct(ChecklistTemplate $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['owner'])) {
            $query->where('owner', $filters['owner']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->withCount('versions')->latest();
    }
}
