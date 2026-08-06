<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Providers;

use Illuminate\Support\ServiceProvider;

final class FlexibleLayoutsServiceProvider extends ServiceProvider
{
    /**
     * mergeConfigFrom must be called in register() so other providers'
     * boot() methods see the merged config.
     *
     * @see https://laravel.com/docs/12.x/package-development#configuration
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/flexible-layouts.php', 'flexible-layouts');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'flexible-layouts');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'flexible-layouts');
        $this->loadRoutesFrom(__DIR__.'/../../routes/moonshine.php');

        $this->publishes([
            __DIR__.'/../../dist' => public_path('vendor/flexible-layouts'),
        ], ['flexible-layouts', 'laravel-assets']);

        $this->publishes([
            __DIR__.'/../../config/flexible-layouts.php' => config_path('flexible-layouts.php'),
        ], ['flexible-layouts-config', 'flexible-layouts']);

        $this->publishes([
            __DIR__.'/../../lang' => lang_path('vendor/flexible-layouts'),
        ], ['flexible-layouts-lang', 'flexible-layouts']);
    }
}
