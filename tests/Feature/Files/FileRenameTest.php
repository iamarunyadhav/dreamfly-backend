<?php

namespace Tests\Feature\Files;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Tests\TestCase;

class FileRenameTest extends TestCase
{
    private function file(): File
    {
        Storage::disk('local')->put('files/test/original.pdf', 'content');
        $folder = Folder::create([
            'name' => 'Rename Folder',
            'slug' => 'rename-folder',
            'scope' => 'global',
            'is_active' => true,
        ]);

        return File::create([
            'folder_id' => $folder->id,
            'name' => 'image-20260803-060710.png',
            'original_name' => 'image-20260803-060710.png',
            'disk' => 'local',
            'path' => 'files/test/original.pdf',
            'extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 14,
        ]);
    }

    public function test_a_permitted_user_can_rename_a_document(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('files.create');
        $file = $this->file();

        $response = $this->actingAs($user)->patchJson("/api/v1/files/{$file->id}/rename", [
            'name' => 'Passport observation page',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Passport observation page');
        $this->assertDatabaseHas('files', [
            'id' => $file->id,
            'original_name' => 'Passport observation page',
            'name' => 'image-20260803-060710.png',
            'path' => 'files/test/original.pdf',
        ]);
    }

    public function test_rename_requires_a_non_empty_name(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('files.create');
        $file = $this->file();

        $this->actingAs($user)->patchJson("/api/v1/files/{$file->id}/rename", ['name' => ''])
            ->assertStatus(422);
    }

    public function test_user_without_permission_cannot_rename(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $file = $this->file();

        $this->actingAs($user)->patchJson("/api/v1/files/{$file->id}/rename", ['name' => 'New name'])
            ->assertStatus(403);
    }
}
