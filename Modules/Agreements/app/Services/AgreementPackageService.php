<?php

namespace Modules\Agreements\Services;

use Illuminate\Support\Facades\URL;
use Modules\Agreements\Models\Agreement;

class AgreementPackageService
{
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'm4v', 'webm'];

    public function defaultVideo(): ?array
    {
        $path = $this->defaultVideoPath();

        if (! $path) {
            return null;
        }

        return [
            'name' => basename($path),
            'size' => filesize($path) ?: 0,
            'mime_type' => mime_content_type($path) ?: 'video/mp4',
            'url' => URL::temporarySignedRoute('api.agreements.default-video', now()->addDays(7)),
        ];
    }

    public function defaultVideoPath(): ?string
    {
        $directories = [
            storage_path('video'),
            storage_path('app/video'),
            storage_path('app/public/video'),
            public_path('storage/video'),
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = [];
            foreach (self::VIDEO_EXTENSIONS as $extension) {
                $files = [...$files, ...glob($directory.DIRECTORY_SEPARATOR.'*.'.$extension) ?: []];
            }

            sort($files, SORT_NATURAL | SORT_FLAG_CASE);

            foreach ($files as $file) {
                if (is_file($file)) {
                    return $file;
                }
            }
        }

        return null;
    }

    public function defaultWelcome(Agreement $agreement): string
    {
        $name = $agreement->client_name ?: 'there';

        return implode("\n", [
            "Hi {$name}, happy to have you with Dream Fly Visa Consultancy.",
            "We are excited to support your visa journey and guide you clearly from the first step.",
            "",
            "வணக்கம் {$name}, Dream Fly Visa Consultancy உடன் உங்கள் visa journey-ஐ தொடங்குவதில் எங்களுக்கு மகிழ்ச்சி.",
            "உங்கள் process-ஐ நாங்கள் தெளிவாகவும் பாதுகாப்பாகவும் guide பண்ணுவோம்.",
            "",
            "Please review the attached agreement and the find out video. If everything is correct, reply Approved / OK.",
            "Agreement சரியாக இருந்தால் Approved / OK என்று reply செய்யுங்கள்.",
        ]);
    }

    public function buildShareBody(
        Agreement $agreement,
        string $agreementLink,
        ?string $videoLink,
        ?string $extraLink,
        ?string $welcome,
        ?string $bankInstructions,
    ): string {
        $sections = [];
        $sections[] = trim((string) $welcome) ?: $this->defaultWelcome($agreement);

        $sections[] = implode("\n", array_filter([
            'Your agreement package:',
            '1. Unsigned Agreement: '.$agreementLink,
            $videoLink ? '2. Find out video: '.$videoLink : null,
            $extraLink ? '3. Additional attachment: '.$extraLink : null,
        ]));

        $bank = trim((string) $bankInstructions);
        if ($bank !== '') {
            $sections[] = "Payment / bank details:\n".$bank;
        }

        $sections[] = implode("\n", [
            'After your confirmation and partial payment, please send us the payment slip.',
            'Then we will open your client file and continue the visa process.',
            'உங்கள் confirmation மற்றும் partial payment slip கிடைத்ததும், client file open செய்து next process தொடங்கப்படும்.',
        ]);

        return implode("\n\n", $sections);
    }
}
