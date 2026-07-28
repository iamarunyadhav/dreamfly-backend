<?php

namespace Tests\Feature\Files;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Files\Models\File;
use Modules\Folders\Models\Folder;
use Tests\TestCase;

class SecureFileAccessTest extends TestCase
{
    private function file(): File
    {
        Storage::disk('local')->put('files/test/secure.txt', 'secure content');
        $folder = Folder::create([
            'name' => 'Secure Folder',
            'slug' => 'secure-folder',
            'scope' => 'global',
            'is_active' => true,
        ]);

        return File::create([
            'folder_id' => $folder->id,
            'name' => 'secure.txt',
            'original_name' => 'secure.txt',
            'disk' => 'local',
            'path' => 'files/test/secure.txt',
            'extension' => 'txt',
            'mime_type' => 'text/plain',
            'size' => 14,
        ]);
    }

    public function test_standard_download_route_requires_authentication(): void
    {
        $file = $this->file();

        $this->getJson("/api/v1/files/{$file->id}/download")->assertUnauthorized();
    }

    public function test_signed_download_route_serves_file_without_public_storage_path(): void
    {
        $file = $this->file();
        $url = URL::temporarySignedRoute('api.files.signed-download', now()->addMinute(), $file->id);

        $response = $this->get($url);

        $response->assertOk();
        $this->assertStringContainsString('attachment;', $response->headers->get('content-disposition'));
    }

    public function test_invalid_signed_download_is_rejected(): void
    {
        $file = $this->file();
        $url = URL::temporarySignedRoute('api.files.signed-download', now()->addMinute(), $file->id).'tampered=1';

        $this->get($url)->assertForbidden();
    }

    public function test_signed_raw_route_serves_the_file_inline_for_browser_preview(): void
    {
        $file = $this->file();
        $url = URL::temporarySignedRoute('api.files.signed-raw', now()->addMinute(), $file->id);

        $response = $this->get($url);

        $response->assertOk();
        $this->assertStringContainsString('inline;', $response->headers->get('content-disposition'));
        $this->assertSame('secure content', $response->streamedContent());
    }

    public function test_share_message_records_temporary_signed_attachment_url(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo('communications.send');
        $file = $this->file();

        $response = $this->actingAs($user)->postJson("/api/v1/files/{$file->id}/share", [
            'channel' => 'email',
            'recipient' => 'client@example.com',
            'subject' => 'Document',
            'body' => 'Please download the document.',
        ]);

        $response->assertCreated();
        $this->assertStringContainsString('/api/v1/files/'.$file->id.'/signed-download', $response->json('data.body'));
        $this->assertStringContainsString('signature=', $response->json('data.body'));
    }
}
