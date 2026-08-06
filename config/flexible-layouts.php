<?php

declare(strict_types=1);

return [

    'route_prefix' => 'flexible-layouts',

    // Logging — off in production by default, on when APP_DEBUG=true.
    // Override explicitly via FLEXIBLE_LAYOUTS_LOGGING=true for prod investigations
    // (e.g. when debugging data loss during a block-type migration).
    // Note: FlexibleCast encode/decode failures always log at Log::error level
    // regardless of this flag — they indicate real data corruption.
    'logging' => env('FLEXIBLE_LAYOUTS_LOGGING', config('app.debug')),

    // Note: Route::moonshine() handles middleware (auth, web, CSRF).
    // Do not add a `middleware` key here — it would be ignored.

];
