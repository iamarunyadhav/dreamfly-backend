<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Requests\UpdateProfileRequest;
use Modules\Users\Http\Resources\UserResource;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::guard('web')->user();

        if ($user->status !== 'active') {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => ['Your account is not active. Please contact an administrator.'],
            ]);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return $this->ok(new UserResource($user->load('roles')), 'Logged in successfully.');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->ok(null, 'Logged out successfully.');
    }

    public function me(Request $request)
    {
        return $this->ok(new UserResource($request->user()->load('roles')));
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $data = $request->safe()->only(['name', 'phone', 'email']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->forceFill($data)->save();

        return $this->ok(new UserResource($user->fresh()->load('roles')), 'Profile updated successfully.');
    }
}
