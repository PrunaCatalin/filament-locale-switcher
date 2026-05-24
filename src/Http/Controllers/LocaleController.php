<?php

declare(strict_types=1);

namespace Prunacatalin\FilamentLocaleSwitcher\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Prunacatalin\FilamentLocaleSwitcher\LocaleResolver;
use Prunacatalin\FilamentLocaleSwitcher\LocaleSwitchPlugin;

class LocaleController
{
    public function switch(Request $request, string $locale)
    {
        $plugin = app(LocaleSwitchPlugin::class);

        if (! in_array($locale, $plugin->getLocales(), true)) {
            abort(Response::HTTP_BAD_REQUEST, 'Unsupported locale.');
        }

        $resolver = new LocaleResolver($plugin);

        // Always update the session — it's the cheapest and most universal
        // store, and lets later requests skip the user-column lookup.
        $request->session()->put($resolver->sessionKey(), $locale);

        $response = redirect($this->safeBackUrl($request));

        match ($plugin->getPersistStrategy()) {
            'user' => $this->persistOnUser($request, $plugin, $locale),
            'cookie' => $response->withCookie(
                // 1-year cookie. Explicit SameSite=Lax so a cross-site
                // request can't trigger or read the saved choice.
                cookie(
                    name: $resolver->cookieKey(),
                    value: $locale,
                    minutes: 525600,
                    path: '/',
                    domain: null,
                    secure: $request->isSecure(),
                    httpOnly: true,
                    raw: false,
                    sameSite: 'lax',
                ),
            ),
            default => null, // 'session' already stored above; nothing more to do.
        };

        return $response;
    }

    /**
     * Write the chosen locale onto the authenticated user model. No-op when
     * the user is a guest or the configured column simply isn't on the model
     * (lets the package coexist with users tables that opted out of the
     * migration).
     */
    protected function persistOnUser(Request $request, LocaleSwitchPlugin $plugin, string $locale): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }

        $column = $plugin->getUserColumn();
        $hasColumn = array_key_exists($column, $user->getAttributes())
            || in_array($column, $user->getFillable(), true);

        if (! $hasColumn) {
            return;
        }

        $user->{$column} = $locale;
        $user->save();
    }

    /**
     * Compute a redirect target that is guaranteed to belong to this host.
     *
     * Without this guard, an attacker can forge a request with a poisoned
     * Referer (or HTTP_REFERER header) and the controller's redirect()->back()
     * would happily point the browser at the attacker's domain. The locale
     * switch itself is non-destructive but the resulting open redirect is
     * still a phishing vector, so we restrict the target to the app host.
     */
    protected function safeBackUrl(Request $request): string
    {
        $previous = (string) url()->previous();
        $previousHost = parse_url($previous, PHP_URL_HOST);

        if ($previousHost !== null && $previousHost !== $request->getHost()) {
            return url('/');
        }

        return $previous;
    }
}
