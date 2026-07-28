<?php

namespace Modules\Workflows\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Workflows\Models\WorkflowTemplate;
use Modules\Workflows\Repositories\Contracts\WorkflowTemplateRepositoryInterface;

class WorkflowTemplateRepository extends BaseRepository implements WorkflowTemplateRepositoryInterface
{
    public function __construct(WorkflowTemplate $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['service_type'])) {
            $query->where('service_type', $filters['service_type']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->latest();
    }
}
