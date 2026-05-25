<?php

namespace VnusWilliams\LaravelAutoLang\Tests;

use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as Orchestra;
use VnusWilliams\LaravelAutoLang\LaravelAutoLangServiceProvider;

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
        $app['config']->set('lang-auto.output', 'json');
    }

    /**
     * Wipe views/ and lang/ after every test so no state leaks between tests.
     */
    protected function tearDown(): void
    {
        $fs = new Filesystem();

        $viewsPath = resource_path('views');
        if ($fs->isDirectory($viewsPath)) {
            $fs->deleteDirectory($viewsPath);
        }

        $langPath = lang_path();
        if ($fs->isDirectory($langPath)) {
            $fs->deleteDirectory($langPath);
        }

        parent::tearDown();
    }
}