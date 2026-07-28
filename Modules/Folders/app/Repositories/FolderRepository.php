<?php

namespace Modules\Folders\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Folders\Models\Folder;
use Modules\Folders\Repositories\Contracts\FolderRepositoryInterface;

class FolderRepository extends BaseRepository implements FolderRepositoryInterface
{
    public function __construct(Folder $model)
    {
        parent::__construct($model);
    }

    public function tree(): Collection
    {
        return $this->query()->orderBy('name')->get();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        if (array_key_exists('root', $filters) && $filters['root']) {
            $query->whereNull('parent_id');
        }

        return $query->orderBy('name');
    }
}
