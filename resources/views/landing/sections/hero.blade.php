{{--
    Hero.

    Two columns on desktop (copy / photo), stacked on mobile with the photo
    first so the salon is the first thing a phone visitor sees. The gold arc and
    bloom are decorative and sit behind the content via z-index.
--}}
@php
    $eyebrow     = $landing->text('hero.eyebrow');
    $titleGold   = $landing->text('hero.title_gold');
    $titleLight  = $landing->text('hero.title_light');
    $description = $landing->text('hero.description');

    $primaryLabel   = $landing->text('hero.primary_cta_label');
    $primaryUrl     = $landing->text('hero.primary_cta_url') ?: route('landing.app');
    $secondaryLabel = $landing->text('hero.secondary_cta_label');
    $secondaryUrl   = $landing->text('hero.secondary_cta_url') ?: '#services';

    // Falls back to the salon photo shipped with the project; the placeholder
    // only appears if that file is removed too.
    $image = $landing->image('hero.image') ?? asset('image/landing/salon.png');
    $badges = $landing->items('hero.badges');
@endphp

<section id="hero" class="relative overflow-hidden pt-28 pb-16 sm:pt-32 lg:pt-36 lg:pb-24">

    {{-- Decoration --}}
    <div class="lp-glow -start-40 -top-32 size-[34rem]" aria-hidden="true"></div>
    <div class="lp-arc -start-72 top-10 size-[46rem]" aria-hidden="true"></div>

    <div class="lp-container relative z-10">
        <div class="grid items-center gap-12 lg:grid-cols-[1fr_1.05fr] lg:gap-14">

            {{-- Copy --}}
            <div class="order-2 lg:order-1">

                @if ($eyebrow)
                    <p class="lp-eyebrow lp-reveal">
                        <x-landing.icon name="scissors" class="size-4" />
                        {{ $eyebrow }}
                    </p>
                @endif

                <h1 class="lp-display lp-reveal mt-5 text-[3.1rem] sm:text-[4.2rem] lg:text-[5.1rem]">
                    @if ($titleGold)
                        <span class="lp-text-gold-grad block">{{ $titleGold }}</span>
                    @endif
                    @if ($titleLight)
                        <span class="block text-cream">{{ $titleLight }}</span>
                    @endif
                </h1>

                @if ($description)
                    <p class="lp-lead lp-reveal mt-6 max-w-lg whitespace-pre-line">{{ $description }}</p>
                @endif

                @if ($primaryLabel || $secondaryLabel)
                    <div class="lp-reveal mt-9 flex flex-wrap items-center gap-3.5">
                        @if ($primaryLabel)
                            <a href="{{ $primaryUrl }}" class="lp-btn lp-btn-gold">
                                <x-landing.icon name="calendar" class="size-4" />
                                {{ $primaryLabel }}
                            </a>
                        @endif

                        @if ($secondaryLabel)
                            <a href="{{ $secondaryUrl }}" class="lp-btn lp-btn-outline">
                                {{ $secondaryLabel }}
                            </a>
                        @endif
                    </div>
                @endif

                @if (count($badges))
                    <ul class="lp-reveal mt-11 flex flex-wrap items-center gap-x-4 gap-y-3">
                        @foreach ($badges as $badge)
                            <li class="flex items-center gap-2 whitespace-nowrap">
                                <span class="lp-badge size-8">
                                    <x-landing.icon :name="$badge['icon'] ?? 'sparkle'" class="size-3.5" />
                                </span>
                                <span class="text-[0.72rem] font-medium text-mist xl:text-[0.78rem]">
                                    {{ $landing->t($badge['label'] ?? null) }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Photo --}}
            <div class="lp-reveal relative order-1 lg:order-2">
                <div class="relative overflow-hidden rounded-2xl border border-gold/20 shadow-[0_40px_100px_-40px_rgba(217,169,40,0.45)]">
                    <img
                        src="{{ $image }}"
                        alt="{{ $landing->text('hero.image_alt', $brandLabel) }}"
                        class="aspect-[4/3] w-full object-cover"
                        width="1456" height="1092"
                        fetchpriority="high"
                    >
                    {{-- Vignette, so the photo sits into the black page rather than on it. --}}
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-ink/70 via-transparent to-transparent"></div>
                </div>
            </div>
        </div>
    </div>
</section>
