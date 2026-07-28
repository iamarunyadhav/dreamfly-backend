<?php

namespace Modules\Agreements\Services;

use App\Support\Service\BaseService;
use Illuminate\Support\Facades\DB;
use Modules\Agreements\Models\Agreement;
use Modules\Agreements\Repositories\Contracts\AgreementRepositoryInterface;

class AgreementService extends BaseService
{
    public function __construct(AgreementRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $attributes): Agreement
    {
        return DB::transaction(function () use ($attributes) {
            $attributes['reference_no'] ??= $this->nextReferenceNo();

            return $this->repository->create($attributes);
        });
    }

    /**
     * DF-AGR-{sequence}-{year}. Loops until a free number is found so a
     * manually-supplied or deleted reference never causes a unique collision.
     * (Prefixed AGR- to stay distinct from Client reference numbers.)
     */
    private function nextReferenceNo(): string
    {
        $year = now()->year;
        $count = Agreement::whereYear('created_at', $year)->count();

        do {
            $count++;
            $ref = sprintf('DF-AGR-%d-%d', $count, $year);
        } while (Agreement::where('reference_no', $ref)->exists());

        return $ref;
    }
}
