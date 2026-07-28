<?php

namespace Modules\Agreements\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Agreements\Models\Agreement;
use Modules\Agreements\Repositories\Contracts\AgreementRepositoryInterface;

class AgreementRepository extends BaseRepository implements AgreementRepositoryInterface
{
    public function __construct(Agreement $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest();
    }
}
