<?php

return [

    // Browsershot shells out to Node.js + a headless Chrome/Chromium to
    // render the branded (agreement, invoice, daily closing, responsibility
    // notice, documentation-unit summary, OCR extraction) PDFs. Leave these
    // null to let Browsershot auto-detect - this usually works on a normal
    // Linux server once `node`/`npm`/`chromium`/`google-chrome` are on
    // $PATH. Set them explicitly on Windows/XAMPP (auto-detection is
    // unreliable there) or when a server has Chrome installed somewhere
    // non-standard.
    'node_binary' => env('BROWSERSHOT_NODE_BINARY'),
    'npm_binary' => env('BROWSERSHOT_NPM_BINARY'),
    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),

    // Set to true if Chrome fails to launch with a sandbox-related error
    // (common on VPS/containers running PHP as root or without user
    // namespaces). Disables Chrome's sandbox - acceptable here since it only
    // ever renders this app's own fixed HTML templates, never arbitrary
    // third-party pages.
    'no_sandbox' => env('BROWSERSHOT_NO_SANDBOX', false),

];
