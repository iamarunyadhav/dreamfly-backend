<?php

namespace Modules\Invoices\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Invoices\Models\Invoice;
use Modules\Invoices\Repositories\Contracts\InvoiceRepositoryInterface;

class InvoiceRepository extends BaseRepository implements InvoiceRepositoryInterface
{
    public function __construct(Invoice $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $query->where('reference_no', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->latest();
    }
}
