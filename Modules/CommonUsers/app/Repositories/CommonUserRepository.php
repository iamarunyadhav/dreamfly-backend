<?php

namespace Modules\CommonUsers\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\CommonUsers\Models\CommonUser;
use Modules\CommonUsers\Repositories\Contracts\CommonUserRepositoryInterface;

class CommonUserRepository extends BaseRepository implements CommonUserRepositoryInterface
{
    public function __construct(CommonUser $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // A converted lead is now the client it became - it clutters the leads
        // list, so it stays hidden here by default. `status=all` is the explicit
        // override that shows everything, and `status=converted` shows only the
        // converted ones (e.g. for audit); any other status filters exactly.
        if (($filters['status'] ?? null) === 'all') {
            // no-op: every status included
        } elseif (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        } else {
            $query->where('status', '!=', 'converted');
        }

        if (! empty($filters['service_category'])) {
            $query->where('service_category', $filters['service_category']);
        }

        if (! empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        // Surface document readiness on every list row so the UI can show
        // "x verified / y uploaded" and gate the convert action.
        $query->withCount(['documents', 'verifiedDocuments']);

        return $query->latest();
    }
}
