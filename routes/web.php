<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Prunacatalin\FilamentLocaleSwitcher\Http\Controllers\LocaleController;

/*
 * POST-only so a forged <img src="/filament-locale/ro"> can't silently change
 * the user's locale, and CSRF protection on the web group catches third-party
 * forms. The blade dropdown ships its own @csrf form; user-menu items use a
 * tiny inline submit form rendered server-side.
 *
 * Throttled at 30 switches/min/IP — locale flips are cheap but a tight cap
 * still blocks log-spam from automated probes.
 */
Route::middleware(['web', 'throttle:30,1'])->group(function (): void {
    Route::post('/filament-locale/{locale}', [LocaleController::class, 'switch'])
        ->name('filament-locale-switcher.switch')
        ->where('locale', '[a-z]{2}(?:[-_][A-Za-z]{2,4})?');
});
