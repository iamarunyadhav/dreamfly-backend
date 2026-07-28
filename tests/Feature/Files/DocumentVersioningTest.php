<?php

namespace Tests\Feature\Files;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Tests\TestCase;

class DocumentVersioningTest extends TestCase
{
    private function staff(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function folder(): Folder
    {
        return Folder::create([
            'name' => 'Versioned Folder',
            'slug' => 'versioned-folder',
            'scope' => 'global',
            'is_active' => true,
        ]);
    }

    private function upload(User $user, Folder $folder): File
    {
        $response = $this->actingAs($user)->postJson('/api/v1/files', [
            'folder_id' => $folder->id,
            'file' => UploadedFile::fake()->create('bank-statement.pdf', 20, 'application/pdf'),
        ]);

        $response->assertCreated();

        return File::findOrFail($response->json('data.id'));
    }

    public function test_first_upload_is_version_one_and_its_own_root(): void
    {
        $user = $this->staff(['files.create', 'files.view']);
        $file = $this->upload($user, $this->folder());

        $this->assertSame(1, (int) $file->version);
        $this->assertSame($file->id, $file->version_root_id);
        $this->assertTrue((bool) $file->is_current);
        $this->assertNull($file->replaces_file_id);
    }

    public function test_new_version_supersedes_the_original_without_deleting_it(): void
    {
        $user = $this->staff(['files.create', 'files.view', 'files.upload']);
        $original = $this->upload($user, $this->folder());

        $response = $this->actingAs($user)->postJson("/api/v1/files/{$original->id}/versions", [
            'file' => UploadedFile::fake()->create('bank-statement-corrected.pdf', 25, 'application/pdf'),
            'version_note' => 'Client sent the corrected statement.',
        ]);

        $response->assertCreated();
        $replacement = File::findOrFail($response->json('data.id'));

        $this->assertSame(2, (int) $replacement->version);
        $this->assertSame($original->id, $replacement->version_root_id);
        $this->assertSame($original->id, $replacement->replaces_file_id);
        $this->assertTrue((bool) $replacement->is_current);
        $this->assertSame('Client sent the corrected statement.', $replacement->version_note);

        // The original survives, flagged as superseded rather than removed.
        $original->refresh();
        $this->assertFalse((bool) $original->is_current);
        $this->assertNotNull($original->superseded_at);
        $this->assertNull($original->deleted_at);
    }

    public function test_a_new_version_is_not_verified_by_inheritance(): void
    {
        $user = $this->staff(['files.create', 'files.view', 'files.upload']);
        $original = $this->upload($user, $this->folder());
        $original->forceFill(['verified' => true, 'verified_at' => now(), 'verified_by' => $user->id])->save();

        $response = $this->actingAs($user)->postJson("/api/v1/files/{$original->id}/versions", [
            'file' => UploadedFile::fake()->create('corrected.pdf', 25, 'application/pdf'),
        ]);

        $this->assertFalse((bool) File::findOrFail($response->json('data.id'))->verified);
    }

    public function test_version_chain_is_listed_oldest_first(): void
    {
        $user = $this->staff(['files.create', 'files.view', 'files.upload']);
        $original = $this->upload($user, $this->folder());

        $v2 = $this->actingAs($user)->postJson("/api/v1/files/{$original->id}/versions", [
            'file' => UploadedFile::fake()->create('v2.pdf', 21, 'application/pdf'),
        ])->json('data.id');

        $this->actingAs($user)->postJson("/api/v1/files/{$v2}/versions", [
            'file' => UploadedFile::fake()->create('v3.pdf', 22, 'application/pdf'),
        ])->assertCreated();

        $response = $this->actingAs($user)->getJson("/api/v1/files/{$original->id}/versions");

        $response->assertOk();
        $this->assertSame([1, 2, 3], array_column($response->json('data'), 'version'));
        $this->assertSame([false, false, true], array_column($response->json('data'), 'is_current'));
    }

    public function test_folder_listing_hides_superseded_versions_unless_asked(): void
    {
        $user = $this->staff(['files.create', 'files.view', 'files.upload']);
        $folder = $this->folder();
        $original = $this->upload($user, $folder);

        $this->actingAs($user)->postJson("/api/v1/files/{$original->id}/versions", [
            'file' => UploadedFile::fake()->create('corrected.pdf', 25, 'application/pdf'),
        ])->assertCreated();

        $current = $this->actingAs($user)->getJson("/api/v1/files?folder_id={$folder->id}");
        $this->assertCount(1, $current->json('data'));
        $this->assertSame(2, $current->json('data.0.version'));

        $all = $this->actingAs($user)->getJson("/api/v1/files?folder_id={$folder->id}&include_superseded=1");
        $this->assertCount(2, $all->json('data'));
    }

    public function test_uploading_a_version_requires_the_upload_permission(): void
    {
        $viewer = $this->staff(['files.create', 'files.view']);
        $original = $this->upload($viewer, $this->folder());

        $this->actingAs($viewer)->postJson("/api/v1/files/{$original->id}/versions", [
            'file' => UploadedFile::fake()->create('corrected.pdf', 25, 'application/pdf'),
        ])->assertStatus(403);
    }
}
