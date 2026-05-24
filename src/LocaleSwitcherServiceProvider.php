<?php

declare(strict_types=1);

namespace Prunacatalin\FilamentLocaleSwitcher;

use Illuminate\Support\ServiceProvider;

class LocaleSwitcherServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge defaults so config('filament-locale-switcher.*') always returns
        // something, even when the consumer hasn't published the config file.
        $this->mergeConfigFrom(
            __DIR__ . '/../config/filament-locale-switcher.php',
            'filament-locale-switcher',
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'filament-locale-switcher');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/filament-locale-switcher.php'
                    => config_path('filament-locale-switcher.php'),
            ], 'filament-locale-switcher-config');
        }
    }
}
