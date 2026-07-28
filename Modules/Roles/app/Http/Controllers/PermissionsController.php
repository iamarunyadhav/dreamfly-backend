<?php

namespace Modules\Roles\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Modules\Roles\Http\Resources\PermissionResource;
use Spatie\Permission\Models\Permission;

class PermissionsController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $permissions = Permission::orderBy('name')->get();

        return $this->ok(PermissionResource::collection($permissions));
    }
}
