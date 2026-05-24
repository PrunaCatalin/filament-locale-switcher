<?php

declare(strict_types=1);

use Prunacatalin\FilamentLocaleSwitcher\LocaleResolver;
use Prunacatalin\FilamentLocaleSwitcher\LocaleSwitchPlugin;
use Prunacatalin\FilamentLocaleSwitcher\Tests\Fixtures\User;

beforeEach(function (): void {
    // Resolve a fresh plugin so each test starts from the env defaults
    // (locales = en/ro/fr/it, persist = session).
    app()->forgetInstance(LocaleSwitchPlugin::class);
    app(LocaleSwitchPlugin::class); // bind concrete singleton from container.
});

it('stores the chosen locale in the session and redirects back', function (): void {
    $resolver = new LocaleResolver(app(LocaleSwitchPlugin::class));

    $response = $this->from('/admin/dashboard')
        ->post('/filament-locale/ro');

    $response->assertRedirect('/admin/dashboard');
    expect(session($resolver->sessionKey()))->toBe('ro');
});

it('rejects a locale outside the whitelist with HTTP 400', function (): void {
    $this->post('/filament-locale/xx')->assertStatus(400);
});

it('rejects GET — switch route is POST-only to defeat <img> CSRF', function (): void {
    $this->get('/filament-locale/ro')->assertStatus(405);
});

it('writes the user column when persist=user is configured', function (): void {
    config()->set('filament-locale-switcher.persist', 'user');
    app()->forgetInstance(LocaleSwitchPlugin::class);

    $user = User::create(['email' => 'a@b.test', 'locale' => null]);

    $this->actingAs($user)
        ->from('/admin')
        ->post('/filament-locale/fr')
        ->assertRedirect('/admin');

    expect($user->fresh()->locale)->toBe('fr');
});

it('sets a forever cookie when persist=cookie is configured', function (): void {
    config()->set('filament-locale-switcher.persist', 'cookie');
    app()->forgetInstance(LocaleSwitchPlugin::class);

    $resolver = new LocaleResolver(app(LocaleSwitchPlugin::class));

    $response = $this->from('/admin')->post('/filament-locale/it');

    $response->assertRedirect('/admin');
    $response->assertCookie($resolver->cookieKey(), 'it');
});

it('still updates the session even when persist=user is configured (so guests work too)', function (): void {
    config()->set('filament-locale-switcher.persist', 'user');
    app()->forgetInstance(LocaleSwitchPlugin::class);

    $resolver = new LocaleResolver(app(LocaleSwitchPlugin::class));

    // No user logged in — the user-write branch is skipped, but the session
    // store always gets the value so the resolver chain still finds it.
    $this->from('/admin')->post('/filament-locale/ro')->assertRedirect('/admin');

    expect(session($resolver->sessionKey()))->toBe('ro');
});

it('refuses to redirect to a different host (open-redirect guard)', function (): void {
    // Simulate a poisoned Referer pointing at attacker.com — the controller
    // must NOT bounce the browser there. Falls back to the app root instead.
    $response = $this->from('https://attacker.com/phish')
        ->post('/filament-locale/ro');

    $response->assertRedirect('/');
});
