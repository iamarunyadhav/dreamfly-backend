<?php

namespace Modules\Folders\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'parent_id' => $this->parent_id,
            'client_id' => $this->client_id,
            'common_user_id' => $this->common_user_id,
            'template_id' => $this->template_id,
            'scope' => $this->scope,
            'is_general' => $this->is_general,
            'auto_create_for_clients' => $this->auto_create_for_clients,
            'applies_to' => $this->applies_to,
            'is_active' => $this->is_active,
            'public_download' => $this->public_download,
            'files_count' => $this->whenCounted('files'),
            'children_count' => $this->whenCounted('children'),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
