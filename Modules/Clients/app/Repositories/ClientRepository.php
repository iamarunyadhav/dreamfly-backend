<?php

namespace Modules\Clients\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Clients\Models\Client;
use Modules\Clients\Repositories\Contracts\ClientRepositoryInterface;

class ClientRepository extends BaseRepository implements ClientRepositoryInterface
{
    public function __construct(Client $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        return $query->latest();
    }
}
