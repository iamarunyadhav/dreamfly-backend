<?php

namespace Modules\Roles\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Roles\Repositories\Contracts\RoleRepositoryInterface;
use Spatie\Permission\Models\Role;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $query->where('name', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('name');
    }
}
