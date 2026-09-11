@extends('layouts.landing')

{{--
    /page/{slug} — a legal document (Impressum, Datenschutz, AGB) in the site's
    own skin.

    Written for LONG text. These pages are not marketing copy: the privacy
    policy is 78 blocks and 17 numbered sections, and the previous template
    poured all of it into one undifferentiated column with no way to reach a
    clause. This one gives the reader a title block, a contents list that sticks
    while they scroll, an anchor on every section, and a measured line length.

    $doc arrives from App\Services\Landing\LegalDocument — title, sections,
    anchored blocks. The template does no walking of its own; see that class for
    why the title comes from the first heading block and not from $page->name.

    Per the CMS contract an unknown block type is skipped silently, so a block
    type added to the CMS later degrades instead of breaking the page.
--}}

@section('title', $doc->title)

@php
    /*
     * PHYSICAL classes on purpose — text-left/right, not text-start/end.
     *
     * CmsPageTransformer has already resolved each block's `alignment: auto`
     * into a concrete PHYSICAL side for the requested language: "right" for
     * Arabic, "left" for German. Mapping that onto a logical class flips it a
     * second time, and RTL's text-end is the LEFT edge — which is how every
     * Arabic paragraph on these pages ended up hugging the wrong margin inside
     * a correctly `dir="rtl"` document. The direction is applied once, upstream.
     */
    $align = fn (?string $value) => match ($value) {
        'right'   => 'text-right',
        'center'  => 'text-center',
        'justify' => 'text-justify',
        default   => 'text-left',
    };
@endphp

@section('content')

    {{-- ── Document header ──────────────────────────────────────────────── --}}
    <header class="relative overflow-hidden border-b border-gold/12 bg-ink-soft pt-32 pb-12 lg:pt-36 lg:pb-14">
        {{-- Offset only, no -translate-x: `start-1/2` is logical and the
             translate is physical, so the pair centres the glow in LTR and
             throws it a full width off-centre in RTL. Every other section
             positions its glow with logical offsets alone. --}}
        <div class="lp-glow -start-40 -top-40 size-[34rem] opacity-40" aria-hidden="true"></div>

        <div class="lp-container relative">
            <p class="lp-eyebrow">{{ __('landing.legal_eyebrow') }}</p>

            <h1 class="lp-title mt-3">{{ $doc->title }}</h1>
            <div class="lp-rule"></div>

            @if ($doc->updatedAt)
                <p class="mt-6 flex items-center gap-2 text-[0.78rem] text-ash">
                    <x-landing.icon name="clock" class="size-3.5 text-gold" />
                    {{ __('landing.legal_updated', ['date' => $doc->updatedAt]) }}
                </p>
            @endif
        </div>
    </header>

    <div class="lp-section">
        <div class="lp-container grid gap-10 lg:grid-cols-[16rem_minmax(0,1fr)] lg:gap-14">

            {{-- ── Contents ─────────────────────────────────────────────────
                 Desktop: a sticky rail. Mobile: a collapsed <details>, the same
                 no-JavaScript pattern the language switcher uses — a legal page
                 must be readable with scripting off. --}}
            @if ($doc->hasContents())
                <aside class="lg:sticky lg:top-28 lg:self-start">
                    <details class="lp-panel group p-5 lg:pointer-events-none" open>
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between gap-3 lg:cursor-default"
                        >
                            <span class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-gold">
                                {{ __('landing.legal_contents') }}
                            </span>
                            <x-landing.icon
                                name="chevron-down"
                                class="size-4 shrink-0 text-ash transition-transform group-open:rotate-180 lg:hidden"
                            />
                        </summary>

                        <nav aria-label="{{ __('landing.legal_contents') }}" class="lg:pointer-events-auto">
                            {{-- No generated numbering.

                                 These documents number their own clauses, in
                                 their own script — "1. Anbieter der App" in
                                 German, "١. مزود التطبيق" in Arabic. Prefixing a
                                 counter of our own printed each entry twice
                                 ("01  ١. مزود التطبيق") and in two different
                                 numeral systems. A document that numbers itself
                                 is the authority on its own numbering; the ones
                                 that do not (the Impressum) read fine as a plain
                                 list. --}}
                            <ul class="mt-5 max-h-[60vh] space-y-2 overflow-y-auto pe-1">
                                @foreach ($doc->sections as $section)
                                    <li>
                                        <a
                                            href="#{{ $section['id'] }}"
                                            class="flex gap-2.5 text-[0.78rem] leading-snug text-ash transition-colors hover:text-gold"
                                        >
                                            <span class="mt-1.5 size-1 shrink-0 rounded-full bg-gold/50"></span>
                                            <span>{{ $section['text'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </nav>
                    </details>
                </aside>
            @endif

            {{-- ── The document itself ──────────────────────────────────── --}}
            <article class="max-w-2xl space-y-6">
                @foreach ($doc->blocks as $block)
                    @php
                        // Cast: a divider carries `content` as an empty stdClass,
                        // not an empty array — see LegalDocument::build().
                        $props     = (array) ($block['props'] ?? []);
                        $content   = (array) ($block['content'] ?? []);
                        $alignment = $align($props['alignment'] ?? null);
                    @endphp

                    @switch($block['type'] ?? null)

                        @case('heading')
                            {{-- scroll-mt clears the fixed navbar, so following an
                                 anchor does not park the heading underneath it. --}}
                            <h2
                                @isset($block['anchor']) id="{{ $block['anchor'] }}" @endisset
                                class="lp-display scroll-mt-28 pt-6 text-lg text-cream sm:text-xl {{ $alignment }}"
                            >
                                {{ $content['text'] ?? '' }}
                            </h2>
                            @break

                        @case('paragraph')
                            <p class="whitespace-pre-line text-[0.92rem] leading-[1.85] text-mist {{ $alignment }}">
                                {{ $content['text'] ?? '' }}
                            </p>
                            @break

                        @case('title_paragraph')
                            <div class="rounded-xl border border-gold/12 bg-ink-raised/60 px-5 py-4 {{ $alignment }}">
                                <h3 class="text-[0.95rem] font-semibold text-cream">{{ $content['title'] ?? '' }}</h3>
                                <p class="mt-2 whitespace-pre-line text-[0.9rem] leading-[1.8] text-mist">
                                    {{ $content['text'] ?? '' }}
                                </p>
                            </div>
                            @break

                        @case('ordered_list')
                            <ol class="space-y-2.5 {{ $alignment }}">
                                @foreach ($content['items'] ?? [] as $index => $item)
                                    <li class="flex gap-3 text-[0.92rem] leading-[1.8] text-mist">
                                        <span class="shrink-0 tabular-nums font-semibold text-gold">{{ $index + 1 }}.</span>
                                        <span>{{ is_array($item) ? ($item['value'] ?? '') : $item }}</span>
                                    </li>
                                @endforeach
                            </ol>
                            @break

                        @case('unordered_list')
                            <ul class="space-y-2.5 {{ $alignment }}">
                                @foreach ($content['items'] ?? [] as $item)
                                    <li class="flex gap-3 text-[0.92rem] leading-[1.8] text-mist">
                                        <span class="mt-2.5 size-1.5 shrink-0 rounded-full bg-gold"></span>
                                        <span>{{ is_array($item) ? ($item['value'] ?? '') : $item }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @break

                        @case('divider')
                            <div class="lp-hairline h-px w-full"></div>
                            @break

                        @case('link')
                            <div class="{{ $alignment }}">
                                <a
                                    href="{{ $content['url'] ?? '#' }}"
                                    class="lp-btn lp-btn-outline lp-btn-sm"
                                    @if (($props['target'] ?? 'same') === 'external') target="_blank" rel="noopener" @endif
                                >
                                    {{ $content['label'] ?? '' }}
                                </a>
                            </div>
                            @break

                        @case('image')
                            <figure class="{{ $alignment }}">
                                <img
                                    src="{{ $content['url'] ?? '' }}"
                                    alt="{{ $content['alt'] ?? '' }}"
                                    class="mx-auto max-w-full rounded-xl border border-gold/20"
                                    loading="lazy"
                                >
                            </figure>
                            @break

                        @case('warning_box')
                            <div class="flex gap-3.5 rounded-xl border border-gold/35 bg-gold/8 px-5 py-4 {{ $alignment }}">
                                <x-landing.icon name="shield" class="mt-0.5 size-5 shrink-0 text-gold" />
                                <p class="whitespace-pre-line text-[0.9rem] leading-[1.8] text-cream">
                                    {{ $content['text'] ?? '' }}
                                </p>
                            </div>
                            @break

                        @case('html')
                            {{-- Sanitised upstream by the CMS; see CMS_FRONTEND_GUIDE.md. --}}
                            <div class="text-[0.92rem] leading-[1.8] text-mist {{ $alignment }} [&_a]:text-gold [&_a:hover]:underline [&_strong]:text-cream">
                                {!! $content['html'] ?? '' !!}
                            </div>
                            @break

                        @default
                            {{-- Unknown block type: skip it, per the CMS contract. --}}
                    @endswitch
                @endforeach

                {{-- Sibling legal pages. Someone reading the AGB is one click from
                     the Impressum they were actually looking for. --}}
                @if (count($siblingLegalLinks))
                    <nav aria-label="{{ __('landing.legal_other') }}" class="!mt-14 border-t border-gold/12 pt-8">
                        <h2 class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-gold">
                            {{ __('landing.legal_other') }}
                        </h2>
                        <ul class="mt-4 flex flex-wrap gap-2.5">
                            @foreach ($siblingLegalLinks as $link)
                                <li>
                                    <a href="{{ $landing->linkUrl($link['url'] ?? null) }}"
                                       class="lp-btn lp-btn-outline lp-btn-sm">
                                        {{ $landing->t($link['label'] ?? null) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                {{-- `lp-back` flips the arrow against the reading direction in
                     both scripts; see the directional mirrors in landing.css. --}}
                <a href="{{ route('landing') }}" class="lp-more lp-back !mt-10">
                    <x-landing.icon name="arrow-right" class="size-3.5" />
                    {{ __('landing.legal_back') }}
                </a>
            </article>
        </div>
    </div>
@endsection
