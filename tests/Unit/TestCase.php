<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Tests\Unit;

use MoonShine\Laravel\Providers\MoonShineServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use Povly\FlexibleLayouts\Providers\FlexibleLayoutsServiceProvider;

/**
 * Shared base: boots the MoonShine service provider so core singletons
 * (assets, view renderer) are available for component construction.
 * The package provider is booted too so field routes exist in tests.
 */
abstract class TestCase extends TestbenchTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            MoonShineServiceProvider::class,
            FlexibleLayoutsServiceProvider::class,
        ];
    }
}
