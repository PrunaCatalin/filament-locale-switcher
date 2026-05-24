<?php

declare(strict_types=1);

namespace Prunacatalin\FilamentLocaleSwitcher;

use Illuminate\Http\Request;

/**
 * Picks the locale to apply for an incoming request. Priority chain:
 *   1. ?lang=xx       — one-off override (deep link friendly)
 *   2. session value  — last user choice in this browser
 *   3. cookie value   — survives logout when persist=cookie
 *   4. user column    — authenticated user's saved preference
 *   5. Accept-Language header
 *   6. config('app.locale')
 *
 * Any value not present in the plugin's whitelist is rejected — a tampered
 * session can never load an arbitrary lang path.
 */
class LocaleResolver
{
    public function __construct(protected LocaleSwitchPlugin $plugin) {}

    public function resolve(Request $request): string
    {
        $allowed = $this->plugin->getLocales();
        $default = config('app.locale', 'en');

        $candidates = [
            (string) $request->query('lang', ''),
            $this->fromSession($request),
            (string) $request->cookie($this->cookieKey(), ''),
            $this->userPreference($request),
            $this->fromHeader($request, $allowed),
            $default,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && in_array($candidate, $allowed, true)) {
                return $candidate;
            }
        }

        return $allowed[0] ?? $default;
    }

    /**
     * Session lookup that survives the middleware running before StartSession.
     * When the session driver hasn't bound a store yet, we fall through to the
     * next candidate instead of crashing the request.
     */
    protected function fromSession(Request $request): string
    {
        if (! $request->hasSession()) {
            return '';
        }

        try {
            return (string) $request->session()->get($this->sessionKey(), '');
        } catch (\RuntimeException) {
            return '';
        }
    }

    public function sessionKey(): string
    {
        return 'filament_locale_switcher.locale';
    }

    public function cookieKey(): string
    {
        return 'filament_locale_switcher_locale';
    }

    protected function userPreference(Request $request): ?string
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        $column = $this->plugin->getUserColumn();
        $value = $user->{$column} ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<int, string>  $allowed
     */
    protected function fromHeader(Request $request, array $allowed): ?string
    {
        $header = (string) $request->header('Accept-Language', '');
        if ($header === '') {
            return null;
        }

        foreach (explode(',', $header) as $entry) {
            $code = strtolower(substr(trim(explode(';', $entry)[0]), 0, 2));
            if (in_array($code, $allowed, true)) {
                return $code;
            }
        }

        return null;
    }
}
