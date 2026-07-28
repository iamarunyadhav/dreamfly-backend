<?php

namespace Modules\Communications\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Communications\Models\Message;
use Modules\Communications\Repositories\Contracts\MessageRepositoryInterface;

class MessageRepository extends BaseRepository implements MessageRepositoryInterface
{
    public function __construct(Message $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['message_template_id'])) {
            $query->where('message_template_id', $filters['message_template_id']);
        }

        return $query->latest();
    }
}
