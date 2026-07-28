<?php

namespace Modules\CommonUsers\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Modules\CommonUsers\Models\CommonUser;
use Modules\CommonUsers\Repositories\Contracts\CommonUserRepositoryInterface;

class CommonUserService extends BaseService
{
    public function __construct(CommonUserRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $attributes): CommonUser
    {
        $attributes['status'] = $this->deriveStatus(
            (int) ($attributes['agreement_amount'] ?? 0),
            (int) ($attributes['paid_amount'] ?? 0),
        );

        return parent::create($attributes);
    }

    public function update(Model $model, array $attributes): CommonUser
    {
        /** @var CommonUser $model */
        // A converted lead is locked to that status - don't let a fee edit
        // silently pull it back into the unpaid/partially_paid lifecycle.
        if (($attributes['status'] ?? null) === 'converted') {
            return parent::update($model, $attributes);
        }

        if ($model->status !== 'converted') {
            $agreement = (int) ($attributes['agreement_amount'] ?? $model->agreement_amount);
            $paid = (int) ($attributes['paid_amount'] ?? $model->paid_amount);
            $attributes['status'] = $this->deriveStatus($agreement, $paid);
        }

        return parent::update($model, $attributes);
    }

    /**
     * unpaid → partially_paid → fully_paid, based on how much of the
     * agreed fee has been collected.
     */
    private function deriveStatus(int $agreementAmount, int $paidAmount): string
    {
        if ($paidAmount <= 0) {
            return 'unpaid';
        }

        if ($agreementAmount > 0 && $paidAmount >= $agreementAmount) {
            return 'fully_paid';
        }

        return 'partially_paid';
    }
}
