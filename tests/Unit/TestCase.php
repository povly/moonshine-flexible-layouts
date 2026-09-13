<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Tests\Unit;

use MoonShine\Laravel\Providers\MoonShineServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

/**
 * Shared base: boots the MoonShine service provider so core singletons
 * (assets, view renderer) are available for component construction.
 */
abstract class TestCase extends TestbenchTestCase
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [MoonShineServiceProvider::class];
    }
}
