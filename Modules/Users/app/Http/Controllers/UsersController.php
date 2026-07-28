<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;
use Modules\Users\Http\Resources\UserResource;
use Modules\Users\Services\UserService;

class UsersController extends Controller
{
    use ApiResponse;

    public function __construct(protected UserService $service)
    {
    }

    public function index(Request $request)
    {
        $users = $this->service->paginate(
            perPage: (int) $request->integer('per_page', 15),
            with: ['roles'],
            filters: $request->only(['search', 'status', 'role']),
        );

        return $this->ok(UserResource::collection($users));
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->service->create($request->validated());

        return $this->created(new UserResource($user->load('roles')));
    }

    public function show(User $user)
    {
        return $this->ok(new UserResource($user->load('roles')));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->service->update($user, $request->validated());

        return $this->ok(new UserResource($user->load('roles')), 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $this->service->delete($user);

        return $this->noContent();
    }
}
