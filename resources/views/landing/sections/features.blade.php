{{--
    "Warum LOOKUP Friseur? / Mehr als nur ein Friseurbesuch"

    Split layout: pitch on one side, a 2×2 grid of value boxes on the other.
--}}
@php
    $items    = $landing->items('features.items');
    $ctaLabel = $landing->text('features.cta_label');
    $ctaUrl   = $landing->text('features.cta_url') ?: '#info';
@endphp

@if (count($items) || $landing->text('features.title'))
    <section id="features" class="lp-section relative overflow-hidden">

        <div class="lp-glow -end-32 top-1/4 size-[26rem]" aria-hidden="true"></div>

        <div class="lp-container relative z-10">
            <div class="grid items-center gap-12 lg:grid-cols-[0.85fr_1.15fr] lg:gap-16">

                {{-- Pitch --}}
                <div>
                    @include('landing.partials.section-heading', [
                        'eyebrow'     => $landing->text('features.eyebrow'),
                        'title'       => $landing->text('features.title'),
                        'description' => $landing->text('features.description'),
                        'centered'    => false,
                    ])

                    @if ($ctaLabel)
                        <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-gold lp-reveal mt-8">
                            {{ $ctaLabel }}
                        </a>
                    @endif
                </div>

                {{-- Value boxes --}}
                @if (count($items))
                    <div class="grid gap-5 sm:grid-cols-2">
                        @foreach ($items as $item)
                            <div class="lp-panel lp-reveal p-6">
                                <span class="lp-badge lp-badge-lg">
                                    <x-landing.icon :name="$item['icon'] ?? 'sparkle'" class="size-5" />
                                </span>

                                <h3 class="mt-4 text-[0.95rem] font-semibold text-cream">
                                    {{ $landing->t($item['title'] ?? null) }}
                                </h3>

                                @if ($description = $landing->t($item['description'] ?? null))
                                    <p class="mt-2 text-[0.82rem] leading-relaxed text-ash">
                                        {{ $description }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
