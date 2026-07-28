<?php

namespace Modules\Finance\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Finance\Models\LedgerEntry;
use Modules\Finance\Repositories\Contracts\LedgerEntryRepositoryInterface;

class LedgerEntryRepository extends BaseRepository implements LedgerEntryRepositoryInterface
{
    public function __construct(LedgerEntry $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('description', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['source'])) {
            $query->where('source', $filters['source']);
        }

        if (! empty($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (! empty($filters['from'])) {
            $query->whereDate('entry_date', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('entry_date', '<=', $filters['to']);
        }

        return $query->latest();
    }
}
