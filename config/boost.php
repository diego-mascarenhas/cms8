<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Boost Master Switch
    |--------------------------------------------------------------------------
    */

    'enabled' => env('BOOST_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Boost Browser Logs Watcher
    |--------------------------------------------------------------------------
    |
    | Intercepts console.log/error in the browser and POSTs to /_boost/browser-logs
    | so Cursor Boost can read them. Disable locally if you see CORS / "Failed to
    | send logs" spam in DevTools (those errors are not from app debug code).
    |
    */

    'browser_logs_watcher' => env('BOOST_BROWSER_LOGS_WATCHER', false),

];
