{{--
    Service cards ("UNSERE LEISTUNGEN" / "Für Ihren perfekten Look").

    Content is standalone marketing copy, deliberately not joined to the
    `services` table: these cards carry their own photography, icon and pitch,
    and must not shift when a price or a duration is edited in the booking
    system.
--}}
@php
    $items = $landing->items('services.items');
    $moreLabel = $landing->text('services.link_label');
@endphp

@if (count($items))
    <section id="services" class="lp-section relative">
        <div class="lp-container">

            @include('landing.partials.section-heading', [
                'eyebrow'     => $landing->text('services.eyebrow'),
                'title'       => $landing->text('services.title'),
                'description' => $landing->text('services.description'),
                'centered'    => true,
            ])

            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($items as $item)
                    @php
                        $url = $item['url'] ?? null;
                        $image = $landing->img($item['image'] ?? null)
                            ?? asset('image/landing/placeholders/service-' . (($loop->index % 4) + 1) . '.svg');
                    @endphp

                    <article class="lp-panel lp-reveal group flex flex-col overflow-hidden">

                        {{-- Image + overlapping icon badge, as in the design --}}
                        <div class="relative">
                            <img
                                src="{{ $image }}"
                                alt="{{ $landing->t($item['title'] ?? null) }}"
                                class="aspect-[4/3] w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                loading="lazy" decoding="async"
                                width="640" height="440"
                            >
                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-ink via-ink/30 to-transparent"></div>

                            <span class="lp-badge lp-badge-lg absolute -bottom-6 start-5 bg-ink-raised">
                                <x-landing.icon :name="$item['icon'] ?? 'scissors'" class="size-5" />
                            </span>
                        </div>

                        <div class="flex flex-1 flex-col p-5 pt-10">
                            <h3 class="text-[0.98rem] font-semibold text-cream">
                                {{ $landing->t($item['title'] ?? null) }}
                            </h3>

                            @if ($description = $landing->t($item['description'] ?? null))
                                <p class="mt-2 flex-1 text-[0.82rem] leading-relaxed text-ash">
                                    {{ $description }}
                                </p>
                            @endif

                            @if ($moreLabel && $url)
                                <a href="{{ $url }}" class="lp-more mt-5">
                                    {{ $moreLabel }}
                                    <x-landing.icon name="arrow-right" class="size-3.5" />
                                </a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
