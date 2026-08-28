{{--
    Gallery ("GALERIE / Unsere Arbeiten").

    The design shows an uneven row — four narrow tiles and one wide one in the
    middle. That emphasis is content, not layout, so each row carries its own
    `is_wide` flag and the admin decides which tile gets the extra width.
--}}
@php
    $items    = $landing->items('gallery.items');
    $ctaLabel = $landing->text('gallery.cta_label');
    $ctaUrl   = $landing->text('gallery.cta_url');
@endphp

@if (count($items))
    <section id="gallery" class="lp-section">
        <div class="lp-container">

            @include('landing.partials.section-heading', [
                'eyebrow'     => $landing->text('gallery.eyebrow'),
                'title'       => $landing->text('gallery.title'),
                'description' => $landing->text('gallery.description'),
                'centered'    => true,
            ])

            <div class="mt-12 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ($items as $item)
                    @php
                        $wide  = (bool) ($item['is_wide'] ?? false);
                        $image = $landing->img($item['image'] ?? null)
                            ?? asset('image/landing/placeholders/gallery-' . (($loop->index % 5) + 1) . '.svg');
                        $alt   = $landing->t($item['alt'] ?? null, $brandLabel);
                    @endphp

                    <figure @class([
                        'lp-reveal group relative overflow-hidden rounded-xl border border-gold/15 aspect-[3/4]',
                        // The wide tile drops its own aspect and simply stretches to
                        // the row height the narrow tiles set — computing an aspect for
                        // it would have to account for the grid gap and would drift by a
                        // few pixels at every breakpoint.
                        'lg:col-span-2 lg:aspect-auto' => $wide,
                        'lg:col-span-1' => ! $wide,
                    ])>
                        <img
                            src="{{ $image }}"
                            alt="{{ $alt }}"
                            class="absolute inset-0 h-full w-full object-cover transition-transform duration-700 group-hover:scale-[1.07]"
                            loading="lazy" decoding="async"
                            width="420" height="560"
                        >

                        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-ink/80 via-transparent to-transparent opacity-70 transition-opacity duration-500 group-hover:opacity-40"></div>

                        @if ($caption = $landing->t($item['caption'] ?? null))
                            <figcaption class="absolute inset-x-0 bottom-0 p-3 text-[0.7rem] font-medium uppercase tracking-[0.14em] text-cream">
                                {{ $caption }}
                            </figcaption>
                        @endif
                    </figure>
                @endforeach
            </div>

            @if ($ctaLabel && $ctaUrl)
                <div class="mt-10 flex justify-center">
                    <a href="{{ $ctaUrl }}" class="lp-btn lp-btn-outline lp-reveal" target="_blank" rel="noopener">
                        {{ $ctaLabel }}
                    </a>
                </div>
            @endif
        </div>
    </section>
@endif
