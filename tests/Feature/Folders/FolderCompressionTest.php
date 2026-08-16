<?php

namespace Tests\Feature\Folders;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Tests\TestCase;
use ZipArchive;

class FolderCompressionTest extends TestCase
{
    private function staff(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    public function test_compress_bundles_files_from_the_folder_and_its_subfolders_into_one_zip(): void
    {
        $onlineFolder = Folder::create(['name' => 'Online Folder', 'slug' => 'online-folder', 'scope' => 'global', 'is_active' => true]);
        $subFolder = Folder::create(['name' => 'Extra', 'slug' => 'extra', 'parent_id' => $onlineFolder->id, 'scope' => 'global', 'is_active' => true]);

        Storage::disk('local')->put('files/test/passport.pdf', str_repeat('A', 1000));
        Storage::disk('local')->put('files/test/photo.png', $this->smallPngBytes());
        Storage::disk('local')->put('files/test/nested.pdf', str_repeat('B', 500));

        File::create([
            'folder_id' => $onlineFolder->id, 'name' => 'passport.pdf', 'original_name' => 'passport.pdf',
            'disk' => 'local', 'path' => 'files/test/passport.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
            'size' => 1000, 'is_current' => true,
        ]);
        File::create([
            'folder_id' => $onlineFolder->id, 'name' => 'photo.png', 'original_name' => 'photo.png',
            'disk' => 'local', 'path' => 'files/test/photo.png', 'extension' => 'png', 'mime_type' => 'image/png',
            'size' => strlen($this->smallPngBytes()), 'is_current' => true,
        ]);
        File::create([
            'folder_id' => $subFolder->id, 'name' => 'nested.pdf', 'original_name' => 'nested.pdf',
            'disk' => 'local', 'path' => 'files/test/nested.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
            'size' => 500, 'is_current' => true,
        ]);
        // Superseded versions must never end up in the zip.
        File::create([
            'folder_id' => $onlineFolder->id, 'name' => 'old.pdf', 'original_name' => 'old.pdf',
            'disk' => 'local', 'path' => 'files/test/passport.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf',
            'size' => 1000, 'is_current' => false,
        ]);

        $response = $this->actingAs($this->staff('upload-team.compress'))
            ->postJson("/api/v1/folders/{$onlineFolder->id}/compress");

        $response->assertStatus(201);
        $response->assertJsonPath('data.mime_type', 'application/zip');
        $this->assertDatabaseHas('files', ['id' => $response->json('data.id'), 'folder_id' => $onlineFolder->id]);

        $zipFile = File::find($response->json('data.id'));
        $zip = new ZipArchive();
        $zip->open(Storage::disk('local')->path($zipFile->path));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertCount(3, $names);
        $this->assertContains('passport.pdf', $names);
        $this->assertContains('photo.png', $names);
        $this->assertContains('nested.pdf', $names);
        $this->assertNotContains('old.pdf', $names);
    }

    public function test_user_without_permission_cannot_compress_a_folder(): void
    {
        $folder = Folder::create(['name' => 'Online Folder', 'slug' => 'online-folder-2', 'scope' => 'global', 'is_active' => true]);

        $this->actingAs(User::factory()->create(['status' => 'active']))
            ->postJson("/api/v1/folders/{$folder->id}/compress")
            ->assertStatus(403);
    }

    private function smallPngBytes(): string
    {
        $image = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
