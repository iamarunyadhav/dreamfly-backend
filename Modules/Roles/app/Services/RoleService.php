<?php

namespace Modules\Roles\Services;

use App\Support\Service\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Roles\Repositories\Contracts\RoleRepositoryInterface;
use Spatie\Permission\Models\Role;

class RoleService extends BaseService
{
    public function __construct(RoleRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function create(array $attributes): Role
    {
        return DB::transaction(function () use ($attributes) {
            $permissions = $attributes['permissions'] ?? [];

            // Explicitly pin guard_name to 'web' rather than relying on Spatie's
            // Guard::getDefaultName() fallback: the auth:sanctum middleware calls
            // Auth::shouldUse('sanctum') on every authenticated request, which mutates
            // config('auth.defaults.guard') for the remainder of the request. Since
            // Role has no directly-matching auth provider, Guard::getDefaultName()
            // falls back to that mutated value, silently creating roles with
            // guard_name = 'sanctum' instead of 'web' — which then can't be synced
            // with the (guard_name = 'web') seeded permissions.
            $role = $this->repository->create(
                collect($attributes)->except('permissions')->put('guard_name', $attributes['guard_name'] ?? 'web')->all()
            );

            if (! empty($permissions)) {
                $role->syncPermissions($permissions);
            }

            return $role;
        });
    }

    public function update(Model $model, array $attributes): Role
    {
        return DB::transaction(function () use ($model, $attributes) {
            $permissions = $attributes['permissions'] ?? null;

            /** @var Role $role */
            $role = $this->repository->update($model, collect($attributes)->except('permissions')->all());

            if ($permissions !== null) {
                $role->syncPermissions($permissions);
            }

            return $role;
        });
    }
}
