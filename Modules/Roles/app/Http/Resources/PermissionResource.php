<?php

namespace Modules\Roles\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            // "users.view" -> group: "users", action: "view" (drives the permission-matrix UI)
            'group' => Str::before($this->name, '.'),
            'action' => Str::contains($this->name, '.') ? Str::after($this->name, '.') : $this->name,
        ];
    }
}
