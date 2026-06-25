<?php

declare(strict_types=1);

use Povly\FlexibleLayouts\Http\Controllers\BlockController;

Route::moonshine(static function (): void {
    $prefix = config('flexible-layouts.route_prefix', 'flexible-layouts');

    Route::post("{$prefix}/store/{pageUri}/{resourceUri?}", [BlockController::class, 'store'])
        ->name('flexible-layouts.store');
});
