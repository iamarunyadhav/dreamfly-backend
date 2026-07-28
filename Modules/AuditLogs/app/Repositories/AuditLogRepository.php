<?php

namespace Modules\AuditLogs\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\AuditLogs\Repositories\Contracts\AuditLogRepositoryInterface;
use Spatie\Activitylog\Models\Activity;

class AuditLogRepository extends BaseRepository implements AuditLogRepositoryInterface
{
    public function __construct(Activity $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['log_name'])) {
            $query->where('log_name', $filters['log_name']);
        }

        if (! empty($filters['causer_id'])) {
            $query->where('causer_id', $filters['causer_id']);
        }

        if (! empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        return $query->latest();
    }
}
