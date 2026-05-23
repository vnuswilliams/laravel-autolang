<?php

namespace Vnuswilliams\LaravelAutoLang;

use Illuminate\Support\ServiceProvider;
use Vnuswilliams\LaravelAutoLang\Commands\AutoLangCommand;

class LaravelAutoLangServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/config/lang-auto.php', 'lang-auto');
    }

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
