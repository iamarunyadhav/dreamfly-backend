<?php

namespace Modules\Files\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Files\Models\File;
use Modules\Files\Repositories\Contracts\FileRepositoryInterface;

class FileRepository extends BaseRepository implements FileRepositoryInterface
{
    public function __construct(File $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['folder_id'])) {
            $query->where('folder_id', $filters['folder_id']);
        }

        if (! empty($filters['search'])) {
            $query->where('original_name', 'like', "%{$filters['search']}%");
        }

        // Superseded versions stay out of the folder listing by default; they are
        // reachable through the file's own versions endpoint. Pass
        // include_superseded=1 to see the full history inline.
        if (empty($filters['include_superseded'])) {
            $query->where('is_current', true);
        }

        return $query->latest();
    }
}
