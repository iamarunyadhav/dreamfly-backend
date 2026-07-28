<?php

namespace Tests\Feature\Ocr;

use App\Models\User;
use Modules\System\Models\SystemSetting;
use Tests\TestCase;

class OcrSettingsTest extends TestCase
{
    public function test_ocr_settings_are_saved_with_a_masked_api_key_response(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['ocr.manage']);

        $response = $this->actingAs($user)->putJson('/api/v1/ocr/settings', [
            'enabled' => true,
            'api_key' => 'super-secret-key',
            'max_file_size_mb' => 10,
            'max_pdf_pages' => 3,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.api_key', '********');
        $response->assertJsonPath('data.max_file_size_mb', 10);

        $stored = json_decode(SystemSetting::where('key', 'ocr')->value('value'), true);
        $this->assertSame('super-secret-key', $stored['api_key']);
    }

    public function test_masked_api_key_input_preserves_the_existing_key(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $user->givePermissionTo(['ocr.manage']);

        SystemSetting::create([
            'key' => 'ocr',
            'value' => json_encode(['enabled' => true, 'api_key' => 'existing-key', 'max_file_size_mb' => 15, 'max_pdf_pages' => 5]),
        ]);

        $this->actingAs($user)->putJson('/api/v1/ocr/settings', [
            'enabled' => true,
            'api_key' => '********',
            'max_file_size_mb' => 20,
            'max_pdf_pages' => 5,
        ])->assertOk();

        $stored = json_decode(SystemSetting::where('key', 'ocr')->value('value'), true);
        $this->assertSame('existing-key', $stored['api_key']);
        $this->assertSame(20, $stored['max_file_size_mb']);
    }

    public function test_a_user_without_permission_cannot_view_or_update_ocr_settings(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user)->getJson('/api/v1/ocr/settings')->assertStatus(403);
        $this->actingAs($user)->putJson('/api/v1/ocr/settings', [
            'enabled' => true,
            'api_key' => 'x',
            'max_file_size_mb' => 10,
            'max_pdf_pages' => 3,
        ])->assertStatus(403);
    }
}
