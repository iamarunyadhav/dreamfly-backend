<?php

return [
    'name' => 'Agreements',

    // Browsershot needs these to shell out to a headless Chrome/Chromium instance.
    // Leave null to let Browsershot auto-detect (works on most Linux/macOS hosts);
    // set explicitly on Windows/XAMPP where auto-detection is unreliable.
    'pdf' => [
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
        'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
    ],
];
