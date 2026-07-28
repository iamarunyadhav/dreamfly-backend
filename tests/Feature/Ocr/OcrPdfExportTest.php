<?php

namespace Tests\Feature\Ocr;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Files\Models\File;
use Modules\Ocr\Models\OcrExtraction;
use Tests\TestCase;

class OcrPdfExportTest extends TestCase
{
    public function test_a_completed_extraction_can_be_exported_as_a_pdf_file(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['ocr.view']);

        $upload = UploadedFile::fake()->image('passport.jpg')->size(50);
        $path = $upload->storeAs('files/1', 'passport.jpg', 'local');
        $sourceFile = File::create([
            'name' => 'passport.jpg',
            'original_name' => 'passport.jpg',
            'disk' => 'local',
            'path' => $path,
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => 50 * 1024,
        ]);

        $extraction = OcrExtraction::create([
            'file_id' => $sourceFile->id,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
        $extraction->fields()->create(['sort_order' => 0, 'label' => 'Passport No', 'value' => 'N1234567']);

        $response = $this->actingAs($user)->postJson("/api/v1/ocr/extractions/{$extraction->id}/pdf");

        $response->assertCreated();
        $response->assertJsonPath('data.mime_type', 'application/pdf');
        $this->assertDatabaseHas('files', ['id' => $response->json('data.id'), 'mime_type' => 'application/pdf']);
    }
}
