<?php

declare(strict_types=1);

namespace Prunacatalin\FilamentLocaleSwitcher\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Prunacatalin\FilamentLocaleSwitcher\LocaleSwitcherServiceProvider;

/**
 * Boots a bare Laravel app via Orchestra Testbench and registers the package
 * service provider. Filament itself is NOT booted — none of the tests exercise
 * the panel UI; they target the resolver, controller, middleware and plugin
 * class in isolation.
 */
abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Minimal users table so persist='user' tests can read/write a column.
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('locale', 8)->nullable();
            $table->timestamps();
        });
    }

    protected function getPackageProviders($app): array
    {
        return [LocaleSwitcherServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // EncryptCookies/redirect tests need an encryption key.
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        // Pin a known default locale so tests don't depend on whatever the
        // host project happens to ship in config/app.php.
        $app['config']->set('app.locale', 'en');
        $app['config']->set('session.driver', 'array');

        // Sensible package defaults — individual tests override as needed.
        $app['config']->set('filament-locale-switcher.locales', ['en', 'ro', 'fr', 'it']);
        $app['config']->set('filament-locale-switcher.labels', [
            'en' => 'English',
            'ro' => 'Română',
            'fr' => 'Français',
            'it' => 'Italiano',
        ]);
        $app['config']->set('filament-locale-switcher.persist', 'session');
        $app['config']->set('filament-locale-switcher.user_column', 'locale');
        $app['config']->set('filament-locale-switcher.placement', 'topbar');
    }
}
