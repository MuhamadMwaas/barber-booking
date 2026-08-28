{{--
    Language switcher.

    Built on <details>/<summary> rather than JavaScript so it opens, closes and
    is keyboard-operable with the script disabled — the switcher is the one
    control a visitor who cannot read the current language absolutely needs.

    Each option is a GET to /language/{code}, which writes the choice to the
    session and returns the visitor to the page they were on.
--}}
@php
    $current = $languages->firstWhere('code', $landing->locale()) ?? $languages->first();
@endphp

@if ($languages->count() > 1)
    <details class="relative" data-lp-language>
        <summary
            class="lp-badge lp-badge-md cursor-pointer list-none px-3 [&::-webkit-details-marker]:hidden"
            style="width: auto; border-radius: 9999px;"
            aria-label="{{ __('landing.change_language') }}"
        >
            <span class="flex items-center gap-1.5">
                <x-landing.icon name="globe" class="size-4" />
                <span class="text-[0.7rem] font-semibold uppercase tracking-[0.1em]">
                    {{ strtoupper($current?->code ?? '') }}
                </span>
                <x-landing.icon name="chevron-down" class="size-3.5" />
            </span>
        </summary>

        <ul
            class="absolute end-0 top-[calc(100%+0.6rem)] z-50 min-w-44 overflow-hidden rounded-lg border border-gold/25 bg-ink-raised py-1.5 shadow-[0_24px_60px_-24px_rgba(0,0,0,0.9)]"
        >
            @foreach ($languages as $language)
                <li>
                    <a
                        href="{{ route('landing.language', $language->code) }}"
                        @class([
                            'flex items-center justify-between gap-3 px-4 py-2.5 text-sm transition-colors hover:bg-gold/10',
                            'text-gold' => $language->code === $landing->locale(),
                            'text-mist' => $language->code !== $landing->locale(),
                        ])
                        @if ($language->code === $landing->locale()) aria-current="true" @endif
                        lang="{{ $language->code }}"
                        dir="{{ config("cms.supported_languages.{$language->code}.direction", 'ltr') }}"
                    >
                        <span>{{ $language->native_name ?: $language->name }}</span>

                        @if ($language->code === $landing->locale())
                            <x-landing.icon name="check" class="size-4" />
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </details>
@endif
