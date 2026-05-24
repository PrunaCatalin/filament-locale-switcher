<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Prunacatalin\FilamentLocaleSwitcher\Http\Middleware\ApplyLocale;
use Prunacatalin\FilamentLocaleSwitcher\LocaleResolver;
use Prunacatalin\FilamentLocaleSwitcher\LocaleSwitchPlugin;

it('sets the application locale from the resolved value', function (): void {
    $plugin = app(LocaleSwitchPlugin::class)->locales(['en', 'ro', 'fr']);
    $resolver = new LocaleResolver($plugin);

    $request = Request::create('/admin');
    $session = new Store('test', new ArraySessionHandler(60));
    $session->put($resolver->sessionKey(), 'ro');
    $request->setLaravelSession($session);

    app()->setLocale('en');
    expect(app()->getLocale())->toBe('en');

    (new ApplyLocale)->handle($request, fn ($r) => response('ok'));

    expect(app()->getLocale())->toBe('ro');
});

it('falls back to the default when nothing is stored', function (): void {
    app(LocaleSwitchPlugin::class)->locales(['en', 'ro']);
    config()->set('app.locale', 'en');

    $request = Request::create('/admin');
    $request->setLaravelSession(new Store('test', new ArraySessionHandler(60)));

    app()->setLocale('ro');
    (new ApplyLocale)->handle($request, fn ($r) => response('ok'));

    expect(app()->getLocale())->toBe('en');
});
