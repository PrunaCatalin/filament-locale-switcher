<?php

declare(strict_types=1);

use Prunacatalin\FilamentLocaleSwitcher\LocaleSwitchPlugin;

it('hydrates from the published config on construction', function (): void {
    config()->set('filament-locale-switcher.locales', ['en', 'de']);
    config()->set('filament-locale-switcher.labels', ['en' => 'EN', 'de' => 'DE']);
    config()->set('filament-locale-switcher.persist', 'cookie');
    config()->set('filament-locale-switcher.placement', 'user-menu');

    // Resolve a fresh instance so it reads the just-set config values.
    app()->forgetInstance(LocaleSwitchPlugin::class);
    $plugin = app(LocaleSwitchPlugin::class);

    expect($plugin->getLocales())->toBe(['en', 'de'])
        ->and($plugin->getLabel('de'))->toBe('DE')
        ->and($plugin->getPersistStrategy())->toBe('cookie');
});

it('lets fluent setters override config values', function (): void {
    config()->set('filament-locale-switcher.persist', 'session');

    $plugin = LocaleSwitchPlugin::make()
        ->locales(['en', 'ro'])
        ->persist('user')
        ->userColumn('preferred_locale');

    expect($plugin->getLocales())->toBe(['en', 'ro'])
        ->and($plugin->getPersistStrategy())->toBe('user')
        ->and($plugin->getUserColumn())->toBe('preferred_locale');
});

it('falls back to UPPERCASE label when none is configured', function (): void {
    $plugin = LocaleSwitchPlugin::make()->locales(['en', 'jp']);

    expect($plugin->getLabel('jp'))->toBe('JP');
});

it('marks flags as enabled when a flag map is set', function (): void {
    $plugin = LocaleSwitchPlugin::make()
        ->locales(['en', 'ro'])
        ->flags(['en' => '🇬🇧', 'ro' => '🇷🇴']);

    expect($plugin->isFlagsEnabled())->toBeTrue()
        ->and($plugin->getFlag('ro'))->toBe('🇷🇴')
        ->and($plugin->getFlag('fr'))->toBeNull();
});

it('rejects an unknown persistence strategy', function (): void {
    LocaleSwitchPlugin::make()->persist('blockchain');
})->throws(InvalidArgumentException::class, 'persistence');

it('rejects an unknown placement', function (): void {
    LocaleSwitchPlugin::make()->placement('sidebar');
})->throws(InvalidArgumentException::class, 'placement');

it('reads topbar_offset from config and lets the setter override it', function (): void {
    config()->set('filament-locale-switcher.topbar_offset', '6rem');
    app()->forgetInstance(LocaleSwitchPlugin::class);

    $fromConfig = app(LocaleSwitchPlugin::class);
    expect($fromConfig->getTopbarOffset())->toBe('6rem');

    $overridden = LocaleSwitchPlugin::make()->topbarOffset('80px');
    expect($overridden->getTopbarOffset())->toBe('80px');
});
