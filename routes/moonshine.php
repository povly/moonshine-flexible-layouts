<?php

use Illuminate\Support\Facades\Route;
use Povly\FlexibleLayouts\Http\Controllers\BlockController;

Route::moonshine(static function (): void {
    Route::post('/flexible-layouts/{resourceUri?}', [BlockController::class, 'store'])
        ->name('flexible-layouts.store');
}, withPage: true, withAuthenticate: true);
