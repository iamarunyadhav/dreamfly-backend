<?php

namespace Modules\Ocr\Services;

use Modules\System\Models\SystemSetting;

class OcrSettingsService
{
    private const KEY = 'ocr';
    private const MASK = '********';

    public function getMasked(): array
    {
        $settings = $this->stored();
        $settings['api_key'] = empty($settings['api_key']) ? null : self::MASK;

        return $settings;
    }

    public function getRaw(): array
    {
        return $this->stored();
    }

    public function update(array $payload): array
    {
        $current = $this->stored();
        $merged = array_replace($current, $payload);

        if (($payload['api_key'] ?? null) === null || ($payload['api_key'] ?? '') === '' || $payload['api_key'] === self::MASK) {
            $merged['api_key'] = $current['api_key'] ?? null;
        }

        SystemSetting::updateOrCreate(['key' => self::KEY], ['value' => json_encode($merged)]);

        return $this->getMasked();
    }

    private function stored(): array
    {
        $value = SystemSetting::where('key', self::KEY)->value('value');
        $decoded = $value ? json_decode($value, true) : null;

        return is_array($decoded) ? array_replace($this->defaults(), $decoded) : $this->defaults();
    }

    private function defaults(): array
    {
        return [
            'enabled' => false,
            'api_key' => null,
            'max_file_size_mb' => 15,
            'max_pdf_pages' => 5,
        ];
    }
}
