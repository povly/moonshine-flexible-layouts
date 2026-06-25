<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Providers;

use Illuminate\Support\ServiceProvider;

final class FlexibleLayoutsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'flexible-layouts');
        $this->loadRoutesFrom(__DIR__.'/../../routes/moonshine.php');

        $this->publishes([
            __DIR__.'/../../dist' => public_path('vendor/flexible-layouts'),
        ], ['flexible-layouts', 'laravel-assets']);
    }
}
