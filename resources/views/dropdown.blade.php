@php
    /** @var \Prunacatalin\FilamentLocaleSwitcher\LocaleSwitchPlugin $plugin */
    /** @var string $current */
@endphp

{{--
    All sizing/spacing is inline-styled. The blade lives in vendor/ which
    Filament's Tailwind config doesn't scan, so utility classes like `h-9`
    would never reach the compiled CSS in a production build. We lean on
    Filament's already-shipped `fi-icon-btn` for theme-aware base styling
    and add the dimensions/layout ourselves.
--}}
<div
    x-data="{ open: false }"
    @click.outside="open = false"
    @keydown.escape.window="open = false"
    class="fi-locale-switcher"
    style="
        position:absolute;
        top:0; bottom:0;
        inset-inline-end:{{ $plugin->getTopbarOffset() }};
        display:flex;
        align-items:center;
        z-index:20;
    "
>
    <button
        type="button"
        @click="open = ! open"
        class="fi-icon-btn"
        style="position:relative; display:inline-flex; align-items:center; justify-content:center; width:2.25rem; height:2.25rem; border-radius:.5rem; line-height:1;"
        :aria-expanded="open"
        aria-haspopup="menu"
        title="{{ $plugin->getLabel($current) }}"
    >
        @if ($plugin->isFlagsEnabled() && $plugin->getFlag($current))
            <span style="font-size:1.05rem; line-height:1;">{{ $plugin->getFlag($current) }}</span>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.25rem; height:1.25rem;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418" />
            </svg>
        @endif
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.origin.top.right
        role="menu"
        class="fi-dropdown-panel"
        style="position:absolute; inset-inline-end:0; top:calc(100% + .25rem); z-index:50; min-width:11rem; border-radius:.5rem; padding:.25rem 0; box-shadow:0 10px 15px -3px rgb(0 0 0 / .1), 0 4px 6px -4px rgb(0 0 0 / .1); background:var(--gray-900, #18181b); border:1px solid rgba(255,255,255,.08);"
    >
        @foreach ($plugin->getLocales() as $code)
            @php($active = $code === $current)
            <form method="POST" action="{{ route('filament-locale-switcher.switch', ['locale' => $code]) }}" style="margin:0;">
                @csrf
                <button
                    type="submit"
                    class="fi-dropdown-list-item"
                    style="display:flex; align-items:center; gap:.5rem; width:100%; padding:.5rem .75rem; font-size:.875rem; text-align:start; background:transparent; border:0; cursor:pointer; color:{{ $active ? 'var(--primary-400, #4ade80)' : 'inherit' }}; font-weight:{{ $active ? '600' : '400' }};"
                    role="menuitem"
                >
                    @if ($plugin->isFlagsEnabled() && $plugin->getFlag($code))
                        <span style="font-size:1rem; line-height:1;">{{ $plugin->getFlag($code) }}</span>
                    @endif
                    <span style="flex:1;">{{ $plugin->getLabel($code) }}</span>
                    @if ($active)
                        <span aria-hidden="true">✓</span>
                    @endif
                </button>
            </form>
        @endforeach
    </div>
</div>
