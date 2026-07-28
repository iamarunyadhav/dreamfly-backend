<?php

namespace Modules\Files\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\URL;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'folder_id' => $this->folder_id,
            'common_user_id' => $this->common_user_id,
            'client_id' => $this->client_id,
            'name' => $this->original_name,
            'extension' => $this->extension,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'version' => (int) $this->version,
            'version_root_id' => $this->version_root_id,
            'replaces_file_id' => $this->replaces_file_id,
            'is_current' => (bool) $this->is_current,
            'version_note' => $this->version_note,
            'superseded_at' => $this->superseded_at,
            'versions_url' => route('api.files.versions', $this->id),
            'uploaded_by' => $this->uploaded_by,
            'verified' => (bool) $this->verified,
            'verified_at' => $this->verified_at,
            'verified_by' => $this->verified_by,
            'preview_url' => URL::temporarySignedRoute('api.files.signed-preview', now()->addMinutes(30), $this->id),
            'raw_url' => URL::temporarySignedRoute('api.files.signed-raw', now()->addMinutes(30), $this->id),
            'pdf_url' => route('api.files.generate-pdf', $this->id),
            'share_url' => route('api.files.share', $this->id),
            'download_url' => URL::temporarySignedRoute('api.files.signed-download', now()->addMinutes(30), $this->id),
            'created_at' => $this->created_at,
        ];
    }
}
