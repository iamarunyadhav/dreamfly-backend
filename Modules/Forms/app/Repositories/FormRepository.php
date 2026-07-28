<?php

namespace Modules\Forms\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Forms\Models\Form;
use Modules\Forms\Repositories\Contracts\FormRepositoryInterface;

class FormRepository extends BaseRepository implements FormRepositoryInterface
{
    public function __construct(Form $model)
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

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest();
    }
}
