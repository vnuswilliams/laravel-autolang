<?php

namespace Vnuswilliams\LaravelAutoLang\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Vnuswilliams\LaravelAutoLang\LaravelAutoLangServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaravelAutoLangServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('lang-auto.paths', [resource_path('views')]);
        $app['config']->set('lang-auto.locale', 'en');
    }
}
