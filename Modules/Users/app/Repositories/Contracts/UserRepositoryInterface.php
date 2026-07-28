<?php

namespace Modules\Users\Repositories\Contracts;

use App\Models\User;
use App\Support\Repository\BaseRepositoryInterface;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;
}
