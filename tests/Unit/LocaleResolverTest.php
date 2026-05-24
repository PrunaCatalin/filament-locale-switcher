<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Prunacatalin\FilamentLocaleSwitcher\LocaleResolver;
use Prunacatalin\FilamentLocaleSwitcher\LocaleSwitchPlugin;
use Prunacatalin\FilamentLocaleSwitcher\Tests\Fixtures\User;

/**
 * Builds a request that already has a bound session store. Most resolver
 * paths reach $request->session()->get(...), which throws "Session store
 * not set" without this.
 */
function makeRequest(array $query = [], array $cookies = [], array $headers = [], array $sessionData = []): Request
{
    $request = Request::create('/', 'GET', $query, $cookies);
    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }
    $session = new Store('test_session', new ArraySessionHandler(60));
    foreach ($sessionData as $key => $value) {
        $session->put($key, $value);
    }
    $request->setLaravelSession($session);

    return $request;
}

function makePlugin(): LocaleSwitchPlugin
{
    return LocaleSwitchPlugin::make()
        ->locales(['en', 'ro', 'fr', 'it'])
        ->labels(['en' => 'English', 'ro' => 'Română', 'fr' => 'Français', 'it' => 'Italiano']);
}

it('falls back to the configured app default when nothing else matches', function (): void {
    $resolver = new LocaleResolver(makePlugin());

    expect($resolver->resolve(makeRequest()))->toBe('en');
});

it('honours the ?lang= query parameter ahead of every other source', function (): void {
    $resolver = new LocaleResolver(makePlugin());

    $request = makeRequest(
        query: ['lang' => 'ro'],
        cookies: [(new LocaleResolver(makePlugin()))->cookieKey() => 'fr'],
        sessionData: [(new LocaleResolver(makePlugin()))->sessionKey() => 'it'],
    );

    expect($resolver->resolve($request))->toBe('ro');
});

it('uses the session value when no query override is present', function (): void {
    $plugin = makePlugin();
    $resolver = new LocaleResolver($plugin);

    $request = makeRequest(sessionData: [$resolver->sessionKey() => 'fr']);

    expect($resolver->resolve($request))->toBe('fr');
});

it('uses the cookie value when query and session are empty', function (): void {
    $plugin = makePlugin();
    $resolver = new LocaleResolver($plugin);

    $request = makeRequest(cookies: [$resolver->cookieKey() => 'it']);

    expect($resolver->resolve($request))->toBe('it');
});

it('reads the user model column when persist=user and no earlier source matches', function (): void {
    $user = User::create(['email' => 'a@b.test', 'locale' => 'ro']);

    $plugin = LocaleSwitchPlugin::make()
        ->locales(['en', 'ro', 'fr'])
        ->persist('user')
        ->userColumn('locale');

    $resolver = new LocaleResolver($plugin);

    $request = makeRequest();
    $request->setUserResolver(fn () => $user);

    expect($resolver->resolve($request))->toBe('ro');
});

it('parses the Accept-Language header as a last UI hint', function (): void {
    $resolver = new LocaleResolver(makePlugin());

    $request = makeRequest(headers: ['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8']);

    expect($resolver->resolve($request))->toBe('fr');
});

it('rejects values outside the configured whitelist', function (): void {
    $resolver = new LocaleResolver(makePlugin());

    // Tampered session value not on the whitelist must NOT leak through.
    $request = makeRequest(sessionData: [$resolver->sessionKey() => 'xx-evil']);

    expect($resolver->resolve($request))->toBe('en');
});

it('does not crash when the request has no bound session store', function (): void {
    $resolver = new LocaleResolver(makePlugin());

    // Request::create() produces a request WITHOUT a session — earlier
    // versions of resolve() crashed here with "Session store not set".
    $request = Request::create('/', 'GET', ['lang' => 'ro']);

    expect($resolver->resolve($request))->toBe('ro');
});
