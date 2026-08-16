<?php

namespace App\Support\Pdf;

use Spatie\Browsershot\Browsershot;

/**
 * Single place every branded document (agreement, invoice, daily closing,
 * responsibility notice, documentation-unit summary, OCR extraction) renders
 * its Blade view to PDF through, instead of each service duplicating its own
 * Browsershot setup. Previously 6 near-identical copies existed and one of
 * them (OCR) had no config wiring at all, so it silently ignored the
 * BROWSERSHOT_* env vars entirely.
 *
 * Also points Browsershot at this project's own node_modules (populated by
 * `npm install` on whatever server this runs on) rather than relying on
 * Browsershot's default of looking in the *global* npm root - a global
 * puppeteer install is never a given on a fresh server, and node_modules is
 * git-ignored so it never ships with a git-based deploy.
 */
class BrowsershotPdfRenderer
{
    /**
     * @param array{0: float, 1: float, 2: float, 3: float} $margins [top, right, bottom, left]
     */
    public static function render(string $html, array $margins = [0, 0, 0, 0]): string
    {
        return static::configure(Browsershot::html($html), $margins)->pdf();
    }

    /**
     * @param array{0: float, 1: float, 2: float, 3: float} $margins
     */
    public static function configure(Browsershot $browsershot, array $margins = [0, 0, 0, 0]): Browsershot
    {
        $browsershot->format('A4')
            ->margins(...$margins)
            ->showBackground()
            ->waitUntilNetworkIdle();

        if ($nodeBinary = config('browsershot.node_binary')) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        if ($npmBinary = config('browsershot.npm_binary')) {
            $browsershot->setNpmBinary($npmBinary);
        }

        if ($chromePath = config('browsershot.chrome_path')) {
            $browsershot->setChromePath($chromePath);
        }

        if (is_dir(base_path('node_modules'))) {
            $browsershot->setNodeModulePath(base_path('node_modules'));
        }

        // Many VPS/container setups can't run Chrome's sandbox (no user
        // namespaces, running as root, etc.) - this is the single most
        // common reason Browsershot works on a dev machine and throws on a
        // freshly provisioned server. Opt-in via env, since disabling the
        // sandbox is a real (if small, for a server only rendering our own
        // fixed HTML templates) security trade-off.
        if (config('browsershot.no_sandbox')) {
            $browsershot->noSandbox();
        }

        return $browsershot;
    }
}
