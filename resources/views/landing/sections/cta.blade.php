{{--
    Closing call to action ("Bereit für Ihren neuen Look?").

    A bordered gold-tinted banner: calendar mark, headline, one button, and the
    decorative scissors artwork on the trailing edge.
--}}
@php
    $title       = $landing->text('cta.title');
    $description = $landing->text('cta.description');
    $buttonLabel = $landing->text('cta.button_label');
    $buttonUrl   = $landing->text('cta.button_url') ?: route('landing.app');
@endphp

@if ($title || $buttonLabel)
    <section id="cta" class="relative py-8 sm:py-12">
        <div class="lp-container">
            <div class="lp-reveal relative overflow-hidden rounded-2xl border border-gold/30 px-6 py-10 sm:px-10 lg:px-14"
                 style="background-image: linear-gradient(115deg, rgb(217 169 40 / 0.12), rgb(17 17 17) 55%, rgb(0 0 0));">

                {{-- Decorative scissors, trailing edge. Hidden on small screens
                     where it would collide with the copy. --}}
                <div class="pointer-events-none absolute -end-6 -top-6 hidden text-gold/15 lg:block" aria-hidden="true">
                    <x-landing.icon name="scissors" class="size-64" />
                </div>

                <div class="relative z-10 flex flex-col items-start gap-7 lg:flex-row lg:items-center lg:gap-10">

                    <span class="lp-badge size-20 shrink-0 rounded-2xl">
                        <x-landing.icon :name="$landing->text('cta.icon') ?: 'calendar'" class="size-9" />
                    </span>

                    <div class="flex-1">
                        @if ($title)
                            <h2 class="lp-title text-2xl sm:text-3xl">{{ $title }}</h2>
                        @endif

                        @if ($description)
                            <p class="lp-lead mt-3 max-w-xl">{{ $description }}</p>
                        @endif
                    </div>

                    @if ($buttonLabel)
                        <a href="{{ $buttonUrl }}" class="lp-btn lp-btn-gold shrink-0">
                            {{ $buttonLabel }}
                            <x-landing.icon name="arrow-right" class="size-4" />
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
