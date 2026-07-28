<?php

namespace Modules\Contacts\Repositories;

use App\Support\Repository\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Modules\Contacts\Models\Contact;
use Modules\Contacts\Repositories\Contracts\ContactRepositoryInterface;

class ContactRepository extends BaseRepository implements ContactRepositoryInterface
{
    public function __construct(Contact $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->latest();
    }
}
