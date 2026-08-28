@extends('layouts.landing')

{{--
    /app — the app-download page every "Termin buchen" button leads to.

    Booking itself happens in the mobile app (the web app has no public booking
    flow), so this page's whole job is to explain that and hand the visitor the
    two store links.
--}}

@section('title', $landing->text('app_page.title') ?: $seoTitle)

@section('content')
    @php
        $features   = $landing->items('app_page.features');
        $appStore   = $landing->text('app_page.app_store_url');
        $playStore  = $landing->text('app_page.google_play_url');
        $mockup     = $landing->image('app_page.mockup_image') ?? asset('image/landing/placeholders/app-mockup.svg');
        $qr         = $landing->image('app_page.qr_image');
        $steps      = $landing->items('app_page.steps');
    @endphp

    <section class="relative overflow-hidden pt-28 pb-16 sm:pt-32 lg:pt-36 lg:pb-24">

        <div class="lp-glow -start-32 -top-24 size-[32rem]" aria-hidden="true"></div>
        <div class="lp-arc -start-64 top-20 size-[42rem]" aria-hidden="true"></div>

        <div class="lp-container relative z-10">
            <div class="grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr] lg:gap-16">

                {{-- Copy --}}
                <div>
                    @if ($eyebrow = $landing->text('app_page.eyebrow'))
                        <p class="lp-eyebrow lp-reveal">
                            <x-landing.icon name="device" class="size-4" />
                            {{ $eyebrow }}
                        </p>
                    @endif

                    <h1 class="lp-display lp-reveal mt-5 text-[2.4rem] sm:text-[3.2rem] lg:text-[3.6rem]">
                        <span class="lp-text-gold-grad">{{ $landing->text('app_page.title') }}</span>
                    </h1>

                    @if ($description = $landing->text('app_page.description'))
                        <p class="lp-lead lp-reveal mt-6 max-w-xl whitespace-pre-line">{{ $description }}</p>
                    @endif

                    {{-- Store buttons --}}
                    @if ($appStore || $playStore)
                        <div class="lp-reveal mt-9 flex flex-wrap items-center gap-3.5">
                            @if ($appStore)
                                <a href="{{ $appStore }}" target="_blank" rel="noopener"
                                   class="lp-btn lp-btn-gold">
                                    <x-landing.icon name="apple" class="size-5" />
                                    {{ $landing->text('app_page.app_store_label', 'App Store') }}
                                </a>
                            @endif

                            @if ($playStore)
                                <a href="{{ $playStore }}" target="_blank" rel="noopener"
                                   class="lp-btn lp-btn-outline">
                                    <x-landing.icon name="google-play" class="size-5" />
                                    {{ $landing->text('app_page.google_play_label', 'Google Play') }}
                                </a>
                            @endif
                        </div>
                    @endif

                    @if (! $appStore && ! $playStore)
                        {{-- Nothing to link to yet: say so plainly instead of
                             rendering dead buttons. --}}
                        <p class="lp-reveal mt-8 rounded-lg border border-gold/25 bg-gold/5 px-5 py-4 text-[0.85rem] text-mist">
                            {{ $landing->text('app_page.coming_soon_note', __('landing.app_coming_soon')) }}
                        </p>
                    @endif

                    {{-- QR code for desktop visitors --}}
                    @if ($qr)
                        <div class="lp-reveal mt-9 hidden items-center gap-4 lg:flex">
                            <img src="{{ $qr }}" alt="{{ __('landing.qr_alt') }}"
                                 class="size-28 rounded-lg border border-gold/25 bg-white p-2"
                                 loading="lazy" width="112" height="112">
                            <p class="max-w-[14rem] text-[0.8rem] leading-relaxed text-ash">
                                {{ $landing->text('app_page.qr_note') }}
                            </p>
                        </div>
                    @endif
                </div>

                {{-- Mockup --}}
                <div class="lp-reveal relative mx-auto w-full max-w-sm">
                    <img
                        src="{{ $mockup }}"
                        alt="{{ $landing->text('app_page.title') }}"
                        class="w-full rounded-2xl border border-gold/20 shadow-[0_40px_100px_-40px_rgba(217,169,40,0.5)]"
                        width="560" height="900"
                    >
                </div>
            </div>
        </div>
    </section>

    {{-- Feature list --}}
    @if (count($features))
        <section class="lp-section pt-0">
            <div class="lp-container">
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($features as $feature)
                        <div class="lp-panel lp-reveal p-6">
                            <span class="lp-badge lp-badge-lg">
                                <x-landing.icon :name="$feature['icon'] ?? 'sparkle'" class="size-5" />
                            </span>
                            <h2 class="mt-4 text-[0.95rem] font-semibold text-cream">
                                {{ $landing->t($feature['title'] ?? null) }}
                            </h2>
                            @if ($text = $landing->t($feature['description'] ?? null))
                                <p class="mt-2 text-[0.82rem] leading-relaxed text-ash">{{ $text }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- "How it works" steps --}}
    @if (count($steps))
        <section class="lp-section pt-0">
            <div class="lp-container">
                @include('landing.partials.section-heading', [
                    'eyebrow'     => $landing->text('app_page.steps_eyebrow'),
                    'title'       => $landing->text('app_page.steps_title'),
                    'description' => null,
                    'centered'    => true,
                ])

                <ol class="mt-12 grid gap-6 sm:grid-cols-3">
                    @foreach ($steps as $step)
                        <li class="lp-reveal relative ps-14">
                            <span class="lp-display absolute start-0 top-0 text-4xl text-gold/35">
                                {{ str_pad((string) ($loop->iteration), 2, '0', STR_PAD_LEFT) }}
                            </span>
                            <h3 class="text-[0.95rem] font-semibold text-cream">
                                {{ $landing->t($step['title'] ?? null) }}
                            </h3>
                            @if ($text = $landing->t($step['description'] ?? null))
                                <p class="mt-2 text-[0.82rem] leading-relaxed text-ash">{{ $text }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- Contact strip, so a visitor who would rather phone than install still
         has the salon's details without going back. The landing CTA banner is
         deliberately NOT reused here — its button links to this very page. --}}
    @if ($landing->enabled('info'))
        @include('landing.sections.info')
    @endif
@endsection
