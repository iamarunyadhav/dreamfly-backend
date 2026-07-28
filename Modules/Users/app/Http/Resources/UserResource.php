<?php

namespace Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'status' => $this->status,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')),
            'permissions' => $this->when(
                // Always ship permissions for the caller's own record (login/me responses -
                // the SPA needs these to decide what nav/actions to render) and for the
                // detail view of another user (admin inspecting someone else's access).
                $request->routeIs('*.users.show') || $request->user()?->id === $this->id,
                fn () => $this->getAllPermissions()->pluck('name')
            ),
            'last_login_at' => $this->last_login_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
