<?php

namespace Modules\Users\Services;

use App\Models\User;
use App\Support\Service\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Users\Repositories\Contracts\UserRepositoryInterface;

class UserService extends BaseService
{
    public function __construct(UserRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $attributes): User
    {
        return DB::transaction(function () use ($attributes) {
            $roles = $attributes['roles'] ?? [];
            $attributes['password'] = Hash::make($attributes['password']);

            /** @var User $user */
            $user = $this->repository->create(collect($attributes)->except('roles')->all());

            if (! empty($roles)) {
                $user->syncRoles($roles);
            }

            return $user;
        });
    }

    public function update(\Illuminate\Database\Eloquent\Model $model, array $attributes): User
    {
        return DB::transaction(function () use ($model, $attributes) {
            $roles = $attributes['roles'] ?? null;

            if (! empty($attributes['password'])) {
                $attributes['password'] = Hash::make($attributes['password']);
            } else {
                unset($attributes['password']);
            }

            /** @var User $user */
            $user = $this->repository->update($model, collect($attributes)->except('roles')->all());

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            return $user;
        });
    }
}
