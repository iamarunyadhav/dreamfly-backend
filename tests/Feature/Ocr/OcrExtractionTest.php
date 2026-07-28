<?php

namespace Tests\Feature\Ocr;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Modules\Files\Models\File;
use Modules\Ocr\Models\OcrExtraction;
use Modules\System\Models\SystemSetting;
use Tests\TestCase;

class OcrExtractionTest extends TestCase
{
    private function staff(array|string $permissions): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function enableOcr(): void
    {
        SystemSetting::create([
            'key' => 'ocr',
            'value' => json_encode(['enabled' => true, 'api_key' => 'test-key', 'max_file_size_mb' => 15, 'max_pdf_pages' => 5]),
        ]);
    }

    private function fileWithMime(string $mime): File
    {
        $upload = UploadedFile::fake()->image('passport.jpg')->size(50);
        $path = $upload->storeAs('files/1', 'passport.jpg', 'local');

        return File::create([
            'name' => 'passport.jpg',
            'original_name' => 'passport.jpg',
            'disk' => 'local',
            'path' => $path,
            'extension' => 'jpg',
            'mime_type' => $mime,
            'size' => 50 * 1024,
        ]);
    }

    public function test_it_disabled_a_document_can_be_extracted_and_fields_saved(): void
    {
        $this->enableOcr();
        $user = $this->staff(['ocr.run', 'ocr.view']);
        $file = $this->fileWithMime('image/jpeg');

        Http::fake([
            'vision.googleapis.com/*' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "Passport No: N1234567\nFull Name: Arunpragash Alwar",
                        'pages' => [],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/ocr/files/{$file->id}/run");

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'completed');
        $this->assertCount(2, $response->json('data.fields'));
        $this->assertSame('Passport No', $response->json('data.fields.0.label'));
        $this->assertSame('N1234567', $response->json('data.fields.0.value'));
    }

    public function test_a_vision_failure_leaves_a_failed_extraction_with_a_message(): void
    {
        $this->enableOcr();
        $user = $this->staff(['ocr.run']);
        $file = $this->fileWithMime('image/jpeg');

        Http::fake([
            'vision.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400),
        ]);

        $response = $this->actingAs($user)->postJson("/api/v1/ocr/files/{$file->id}/run");

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('data.error_message', 'API key not valid');
        $this->assertDatabaseHas('ocr_extractions', ['file_id' => $file->id, 'status' => 'failed']);
    }

    public function test_disabled_ocr_never_calls_vision_and_fails_cleanly(): void
    {
        $user = $this->staff(['ocr.run']);
        $file = $this->fileWithMime('image/jpeg');

        Http::preventStrayRequests();

        $response = $this->actingAs($user)->postJson("/api/v1/ocr/files/{$file->id}/run");

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('data.error_message', 'OCR is not enabled in settings.');
    }

    public function test_an_unsupported_mime_type_fails_without_calling_vision(): void
    {
        $this->enableOcr();
        $user = $this->staff(['ocr.run']);
        $file = $this->fileWithMime('video/mp4');

        Http::preventStrayRequests();

        $response = $this->actingAs($user)->postJson("/api/v1/ocr/files/{$file->id}/run");

        $response->assertCreated();
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('data.error_message', 'This file type is not supported for OCR.');
    }

    public function test_a_user_without_permission_cannot_run_ocr(): void
    {
        $this->enableOcr();
        $user = User::factory()->create(['status' => 'active']);
        $file = $this->fileWithMime('image/jpeg');

        $this->actingAs($user)->postJson("/api/v1/ocr/files/{$file->id}/run")->assertStatus(403);
    }

    public function test_a_field_value_can_be_edited_and_marks_it_user_edited(): void
    {
        $this->enableOcr();
        $user = $this->staff(['ocr.run', 'ocr.view']);
        $file = $this->fileWithMime('image/jpeg');

        Http::fake([
            'vision.googleapis.com/*' => Http::response([
                'responses' => [['fullTextAnnotation' => ['text' => "Passport No: N1234567", 'pages' => []]]],
            ], 200),
        ]);

        $extraction = OcrExtraction::findOrFail(
            $this->actingAs($user)->postJson("/api/v1/ocr/files/{$file->id}/run")->json('data.id')
        );
        $field = $extraction->fields()->first();

        $response = $this->actingAs($user)->patchJson("/api/v1/ocr/extractions/{$extraction->id}/fields/{$field->id}", [
            'value' => 'N9999999',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('ocr_extraction_fields', ['id' => $field->id, 'value' => 'N9999999', 'is_user_edited' => true]);
    }
}
