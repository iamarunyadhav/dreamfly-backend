<?php

namespace Modules\Clients\Services;

use App\Support\Service\BaseService;
use Illuminate\Support\Facades\DB;
use Modules\Clients\Models\Client;
use Modules\Clients\Repositories\Contracts\ClientRepositoryInterface;

class ClientService extends BaseService
{
    public function __construct(ClientRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $attributes): Client
    {
        return DB::transaction(function () use ($attributes) {
            $attributes['reference_no'] ??= $this->nextReferenceNo();

            return $this->repository->create($attributes);
        });
    }

    /**
     * DF-{sequence}-{year}. Counts trashed rows too (a soft-deleted client still
     * occupies the unique reference_no index) and loops until a free number is
     * found, so a deleted-then-reconverted lead never collides.
     */
    private function nextReferenceNo(): string
    {
        $year = now()->year;
        $count = Client::withTrashed()->whereYear('created_at', $year)->count();

        do {
            $count++;
            $ref = sprintf('DF-%d-%d', $count, $year);
        } while (Client::withTrashed()->where('reference_no', $ref)->exists());

        return $ref;
    }
}
