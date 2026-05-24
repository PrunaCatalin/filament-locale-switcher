<?php

declare(strict_types=1);

namespace Prunacatalin\FilamentLocaleSwitcher;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Support\Htmlable;
use Prunacatalin\FilamentLocaleSwitcher\Http\Middleware\ApplyLocale;

/**
 * Filament plugin: per-user locale switching with topbar dropdown.
 *
 * Wire it up once per panel:
 *
 *   $panel->plugin(
 *       LocaleSwitchPlugin::make()
 *           ->locales(['en','ro','fr','it'])
 *           ->labels(['en'=>'English','ro'=>'Română','fr'=>'Français','it'=>'Italiano'])
 *           ->persist('user')      // session | user | cookie
 *           ->userColumn('locale') // when persist=user
 *           ->placement('topbar')  // topbar | user-menu | both
 *   );
 *
 * The plugin owns:
 *   - the request middleware that calls app()->setLocale() per request
 *   - the POST route that records the new choice
 *   - the topbar/user-menu UI
 */
class LocaleSwitchPlugin implements Plugin
{
    /** @var array<int, string> */
    protected array $locales;

    /** @var array<string, string> */
    protected array $labels;

    /** @var array<string, string> */
    protected array $flags;

    /**
     * Where to persist the selection.
     *   session — survives until session expires (default; works for guests too)
     *   user    — writes to the auth user model's locale column on switch
     *   cookie  — 1-year cookie; survives logout, not synced cross-device
     */
    protected string $persist;

    protected string $userColumn;

    /** topbar | user-menu | both */
    protected string $placement;

    protected bool $showFlags = false;

    /**
     * CSS distance to leave between the topbar switcher and the right edge
     * of the topbar (i.e. the room reserved for Filament's user-menu).
     */
    protected string $topbarOffset;

    public function __construct()
    {
        // Hydrate from the published (or merged) config so every fluent setter
        // is optional. A panel can still override any value via ->locales(),
        // ->labels(), etc. before $panel->plugin(...).
        $cfg = (array) config('filament-locale-switcher', []);

        $this->locales = array_values(array_unique(array_filter(
            (array) ($cfg['locales'] ?? ['en'])
        )));
        $this->labels = (array) ($cfg['labels'] ?? []);
        $this->flags = (array) ($cfg['flags'] ?? []);
        $this->persist = (string) ($cfg['persist'] ?? 'session');
        $this->userColumn = (string) ($cfg['user_column'] ?? 'locale');
        $this->placement = (string) ($cfg['placement'] ?? 'topbar');
        $this->topbarOffset = (string) ($cfg['topbar_offset'] ?? '4.5rem');
        $this->showFlags = $this->flags !== [];
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'webdirect-locale-switcher';
    }

    /* ---------------- fluent config ---------------- */

    /**
     * @param  array<int, string>  $locales
     */
    public function locales(array $locales): static
    {
        $this->locales = array_values(array_unique(array_filter($locales)));

        return $this;
    }

    /**
     * @param  array<string, string>  $labels  locale-code → display name
     */
    public function labels(array $labels): static
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * @param  array<string, string>  $flags  locale-code → emoji or icon
     */
    public function flags(array $flags): static
    {
        $this->flags = $flags;
        $this->showFlags = true;

        return $this;
    }

    public function persist(string $strategy): static
    {
        if (! in_array($strategy, ['session', 'user', 'cookie'], true)) {
            throw new \InvalidArgumentException("Unknown persistence strategy: {$strategy}");
        }
        $this->persist = $strategy;

        return $this;
    }

    public function userColumn(string $column): static
    {
        $this->userColumn = $column;

        return $this;
    }

    public function placement(string $placement): static
    {
        if (! in_array($placement, ['topbar', 'user-menu', 'both'], true)) {
            throw new \InvalidArgumentException("Unknown placement: {$placement}");
        }
        $this->placement = $placement;

        return $this;
    }

    /**
     * Override the CSS gap reserved between the topbar switcher and the
     * right edge (where Filament's user-menu lives). Any CSS length works:
     * '4.5rem', '60px', 'calc(2rem + 16px)'.
     */
    public function topbarOffset(string $offset): static
    {
        $this->topbarOffset = $offset;

        return $this;
    }

    /* ---------------- read-only accessors ---------------- */

    /** @return array<int, string> */
    public function getLocales(): array
    {
        return $this->locales;
    }

    public function getLabel(string $locale): string
    {
        return $this->labels[$locale] ?? strtoupper($locale);
    }

    public function getFlag(string $locale): ?string
    {
        return $this->flags[$locale] ?? null;
    }

    public function isFlagsEnabled(): bool
    {
        return $this->showFlags;
    }

    public function getPersistStrategy(): string
    {
        return $this->persist;
    }

    public function getUserColumn(): string
    {
        return $this->userColumn;
    }

    public function getPlacement(): string
    {
        return $this->placement;
    }

    public function getTopbarOffset(): string
    {
        return $this->topbarOffset;
    }

    /* ---------------- Plugin lifecycle ---------------- */

    /**
     * Tracks the panel ids that have already pushed their render-hook
     * registrations into Filament. registerRenderHook() lacks an idempotency
     * guard, so registering the plugin on multiple panels (or hot-reloading
     * during dev) would otherwise queue duplicate dropdowns into every
     * panel render.
     *
     * @var array<string, true>
     */
    protected static array $hooksRegisteredFor = [];

    public function register(Panel $panel): void
    {
        // The middleware reads the same plugin instance back on every request.
        app()->instance(self::class, $this);

        // NOTE: do not auto-attach ApplyLocale to $panel->middleware() — when
        // Filament merges plugin-added middleware it lands before the panel's
        // session middleware and the resolver can't read the saved choice.
        // Consumers add `ApplyLocale::class` to their explicit middleware list
        // (after StartSession) for correct ordering.

        $panelId = $panel->getId();
        if (isset(self::$hooksRegisteredFor[$panelId])) {
            return;
        }
        self::$hooksRegisteredFor[$panelId] = true;

        // Same blade dropdown for both placements — only the render hook
        // location differs. We intentionally don't use Filament MenuItem for
        // the user-menu placement: MenuItem only knows about GET URLs, and
        // the switch route is POST-only for CSRF safety.
        //
        // Each panel registers its own callback, but every callback ALSO
        // checks the currently-rendering panel id and bails when it isn't
        // the one this plugin instance was registered against. Without this
        // guard, registering the plugin on N panels would push N copies of
        // the dropdown into every panel's topbar.
        $renderView = function () use ($panelId): Htmlable {
            if (\Filament\Facades\Filament::getCurrentPanel()?->getId() !== $panelId) {
                return new \Illuminate\Support\HtmlString('');
            }

            return view('filament-locale-switcher::dropdown', [
                'plugin' => $this,
                'current' => app()->getLocale(),
            ]);
        };

        if (in_array($this->placement, ['topbar', 'both'], true)) {
            FilamentView::registerRenderHook(PanelsRenderHook::TOPBAR_END, $renderView);
        }

        if (in_array($this->placement, ['user-menu', 'both'], true)) {
            FilamentView::registerRenderHook(PanelsRenderHook::USER_MENU_BEFORE, $renderView);
        }
    }

    public function boot(Panel $panel): void
    {
        // Nothing — registration done in register().
    }
}
