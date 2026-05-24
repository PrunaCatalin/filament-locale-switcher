# Filament Locale Switcher

Drop-in locale switcher for Filament v4/v5 panels. Topbar dropdown (or user-menu items), session / per-user / cookie persistence, explicit locale whitelist, six-step resolution chain.

## Install

```bash
composer require prunacatalin/filament-locale-switcher
```

Publish the config (optional — the plugin works without it, but config is the recommended source of truth):

```bash
php artisan vendor:publish --tag=filament-locale-switcher-config
```

If you intend to persist the user's choice on their account, run the migration to add a `locale` column to `users`:

```bash
php artisan migrate
```

## Configure

`config/filament-locale-switcher.php`:

```php
return [
    'locales'     => ['en', 'ro', 'fr', 'it'],
    'labels'      => [
        'en' => 'English',
        'ro' => 'Română',
        'fr' => 'Français',
        'it' => 'Italiano',
    ],
    'flags'       => [
        'en' => '🇬🇧',
        'ro' => '🇷🇴',
        'fr' => '🇫🇷',
        'it' => '🇮🇹',
    ],
    'persist'        => 'user',     // session | user | cookie
    'user_column'    => 'locale',   // column on User when persist=user
    'placement'      => 'topbar',   // topbar | user-menu | both
    'topbar_offset'  => '4.5rem',   // CSS gap between switcher and user-menu
];
```

## Wire it into your panel

Two lines: register the plugin, append the middleware to your panel middleware list (it MUST run after `StartSession`).

```php
use Prunacatalin\FilamentLocaleSwitcher\Http\Middleware\ApplyLocale;
use Prunacatalin\FilamentLocaleSwitcher\LocaleSwitchPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // …your usual config…
        ->plugin(LocaleSwitchPlugin::make())
        ->middleware([
            // …default Filament middleware (EncryptCookies, StartSession, …)…
            ApplyLocale::class,
        ]);
}
```

That's it. The plugin reads from `config/filament-locale-switcher.php`; the dropdown renders in the panel topbar; clicking a flag persists the choice and reloads the current page in that language.

### Per-panel override

Every config key has a matching fluent setter. Useful when a single Laravel app hosts two panels with different policies:

```php
$panel->plugin(
    LocaleSwitchPlugin::make()
        ->locales(['en', 'de'])      // narrower than the global list
        ->persist('cookie')          // override global persist
        ->placement('user-menu'),    // override global placement
);
```

Setters always win over config.

## Resolution chain (per request)

1. `?lang=xx` query parameter
2. session value (last switch in this browser)
3. cookie (when `persist=cookie`)
4. authenticated user's column (when `persist=user`)
5. `Accept-Language` header
6. `config('app.locale')`

Anything outside the configured `locales` whitelist is rejected — tampered session/cookie values can never load an arbitrary lang path.

## Testing

```bash
composer install
composer test
```

Pest suite uses Orchestra Testbench with SQLite `:memory:`. 21 tests / 39 assertions cover the resolver chain, the plugin's config hydration, the middleware, and every controller persistence branch.

## License

MIT.
