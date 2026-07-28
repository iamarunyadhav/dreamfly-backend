<?php

return [
    'name' => 'Invoices',

    // Browsershot needs these to shell out to a headless Chrome/Chromium instance
    // for the branded invoice-notice PDF. Shares the same env keys as the
    // Agreements PDF so a single Windows/XAMPP setup covers both. Leave null to
    // let Browsershot auto-detect (works on most Linux/macOS hosts).
    'pdf' => [
        'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
        'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
        'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),
    ],
];
