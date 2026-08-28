@extends('layouts.landing')

{{--
    /page/{slug} — a CMS page (Impressum, Datenschutz, AGB …) rendered inside
    the public site.

    $blocks arrives already transformed by CmsPageTransformer: disabled blocks
    removed, translations resolved for the current locale, and `props.alignment`
    turned into a concrete left/right/center/justify value. Per the CMS contract,
    an unknown block type is skipped silently rather than breaking the page — so
    a block added to the CMS later degrades instead of erroring here.
--}}

@section('title', $page->name)

@php
    $align = fn (?string $value) => match ($value) {
        'right'   => 'text-right',
        'center'  => 'text-center',
        'justify' => 'text-justify',
        default   => 'text-left',
    };
@endphp

@section('content')
    <article class="lp-section pt-32 lg:pt-36">
        <div class="lp-container max-w-3xl">

            <h1 class="lp-title">{{ $page->name }}</h1>
            <div class="lp-rule"></div>

            <div class="mt-10 space-y-6">
                @foreach ($blocks as $block)
                    @php
                        $props     = $block['props'] ?? [];
                        $content   = $block['content'] ?? [];
                        $alignment = $align($props['alignment'] ?? null);
                    @endphp

                    @switch($block['type'] ?? null)

                        @case('heading')
                            @php
                                $size = match ($props['level'] ?? 'h2') {
                                    'h1'    => 'text-2xl sm:text-3xl',
                                    'h2'    => 'text-xl sm:text-2xl',
                                    'h3'    => 'text-lg',
                                    default => 'text-base',
                                };
                            @endphp
                            <h2 class="lp-display {{ $size }} {{ $alignment }} pt-4 text-gold">
                                {{ $content['text'] ?? '' }}
                            </h2>
                            @break

                        @case('paragraph')
                            <p class="{{ $alignment }} whitespace-pre-line text-[0.9rem] leading-relaxed text-mist">
                                {{ $content['text'] ?? '' }}
                            </p>
                            @break

                        @case('title_paragraph')
                            <div class="{{ $alignment }}">
                                <h3 class="text-[0.95rem] font-semibold text-cream">{{ $content['title'] ?? '' }}</h3>
                                <p class="mt-1.5 whitespace-pre-line text-[0.9rem] leading-relaxed text-mist">
                                    {{ $content['text'] ?? '' }}
                                </p>
                            </div>
                            @break

                        @case('ordered_list')
                            <ol class="{{ $alignment }} space-y-2 ps-1">
                                @foreach ($content['items'] ?? [] as $index => $item)
                                    <li class="flex gap-3 text-[0.9rem] leading-relaxed text-mist">
                                        <span class="shrink-0 font-semibold text-gold">{{ $index + 1 }}.</span>
                                        <span>{{ is_array($item) ? ($item['value'] ?? '') : $item }}</span>
                                    </li>
                                @endforeach
                            </ol>
                            @break

                        @case('unordered_list')
                            <ul class="{{ $alignment }} space-y-2 ps-1">
                                @foreach ($content['items'] ?? [] as $item)
                                    <li class="flex gap-3 text-[0.9rem] leading-relaxed text-mist">
                                        <span class="mt-2 size-1.5 shrink-0 rounded-full bg-gold"></span>
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
                            <div class="{{ $alignment }} flex gap-3 rounded-lg border border-gold/35 bg-gold/8 px-5 py-4">
                                <x-landing.icon name="shield" class="size-5 shrink-0 text-gold" />
                                <p class="whitespace-pre-line text-[0.88rem] leading-relaxed text-cream">
                                    {{ $content['text'] ?? '' }}
                                </p>
                            </div>
                            @break

                        @case('html')
                            {{-- Sanitised upstream by the CMS; see CMS_FRONTEND_GUIDE.md. --}}
                            <div class="{{ $alignment }} text-[0.9rem] leading-relaxed text-mist [&_a]:text-gold [&_a:hover]:underline [&_strong]:text-cream">
                                {!! $content['html'] ?? '' !!}
                            </div>
                            @break

                        @default
                            {{-- Unknown block type: skip it, per the CMS contract. --}}
                    @endswitch
                @endforeach
            </div>

            <a href="{{ route('landing') }}" class="lp-more mt-12">
                <x-landing.icon name="arrow-right" class="size-3.5 rotate-180" />
                {{ $landing->text('brand.name', 'LOOK UP') }}
            </a>
        </div>
    </article>
@endsection
