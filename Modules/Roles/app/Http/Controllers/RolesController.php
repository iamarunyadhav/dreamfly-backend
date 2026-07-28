<?php

namespace Modules\Roles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Roles\Http\Requests\StoreRoleRequest;
use Modules\Roles\Http\Requests\UpdateRoleRequest;
use Modules\Roles\Http\Resources\RoleResource;
use Modules\Roles\Services\RoleService;
use Spatie\Permission\Models\Role;

class RolesController extends Controller
{
    use ApiResponse;

    public function __construct(protected RoleService $service)
    {
    }

    public function index(Request $request)
    {
        $roles = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            with: ['permissions'],
            filters: $request->only(['search']),
        );

        return $this->ok(RoleResource::collection($roles));
    }

    public function store(StoreRoleRequest $request)
    {
        $role = $this->service->create($request->validated());

        return $this->created(new RoleResource($role->load('permissions')));
    }

    public function show(Role $role)
    {
        return $this->ok(new RoleResource($role->load('permissions')->loadCount('users')));
    }

    public function update(UpdateRoleRequest $request, Role $role)
    {
        $role = $this->service->update($role, $request->validated());

        return $this->ok(new RoleResource($role->load('permissions')), 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        $this->service->delete($role);

        return $this->noContent();
    }
}
