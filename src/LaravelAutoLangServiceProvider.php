<?php

namespace Vnuswilliams\LaravelAutoLang;

use Illuminate\Support\ServiceProvider;
use Vnuswilliams\LaravelAutoLang\Commands\AutoLangCommand;

class LaravelAutoLangServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/lang-auto.php', 'lang-auto');
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/lang-auto.php' => config_path('lang-auto.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                AutoLangCommand::class,
            ]);
        }
    }
}
