<?php

declare(strict_types=1);

namespace Prunacatalin\FilamentLocaleSwitcher\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Prunacatalin\FilamentLocaleSwitcher\LocaleResolver;
use Prunacatalin\FilamentLocaleSwitcher\LocaleSwitchPlugin;

/**
 * Per-request locale binding. Runs inside the Filament panel middleware stack
 * after StartSession, so session/cookie/user lookups all see real values.
 */
class ApplyLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $plugin = app(LocaleSwitchPlugin::class);
        $resolver = new LocaleResolver($plugin);
        $locale = $resolver->resolve($request);

        app()->setLocale($locale);

        return $next($request);
    }
}
