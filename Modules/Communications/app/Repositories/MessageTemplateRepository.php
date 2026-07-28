<?php

namespace Modules\Communications\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Communications\Models\MessageTemplate;
use Modules\Communications\Repositories\Contracts\MessageTemplateRepositoryInterface;

class MessageTemplateRepository extends BaseRepository implements MessageTemplateRepositoryInterface
{
    public function __construct(MessageTemplate $model)
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

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        return $query->latest();
    }
}
