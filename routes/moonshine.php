<?php

declare(strict_types=1);

use Povly\FlexibleLayouts\Http\Controllers\BlockController;

Route::moonshine(static function (): void {
    Route::post('flexible-layouts/store/{pageUri}/{resourceUri?}', [BlockController::class, 'store'])
        ->name('flexible-layouts.store');
});
