{{--
    Contact strip: address, phone, opening hours, social.

    The three left columns are a repeater rather than fixed fields, so a salon
    that also wants "E-Mail" or "Parken" can add it without a code change. The
    social column is separate because its rows render as icon links, not text.
--}}
@php
    $items       = $landing->items('info.items');
    $socialLabel = $landing->text('info.social_label');
    $socialLinks = $landing->items('info.social_links');
@endphp

@if (count($items) || count($socialLinks))
    <section id="info" class="pb-8 sm:pb-12">
        <div class="lp-container">
            <div class="lp-panel lp-reveal grid gap-8 p-7 sm:grid-cols-2 sm:p-9 lg:grid-cols-4">

                @foreach ($items as $item)
                    @php $url = $item['url'] ?? null; @endphp

                    <div class="flex items-start gap-3.5">
                        <span class="lp-badge lp-badge-md">
                            <x-landing.icon :name="$item['icon'] ?? 'map-pin'" class="size-4" />
                        </span>

                        <div class="min-w-0">
                            <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-gold">
                                {{ $landing->t($item['label'] ?? null) }}
                            </p>

                            @php $value = $landing->t($item['value'] ?? null); @endphp

                            @if ($value)
                                @if ($url)
                                    <a href="{{ $url }}" class="mt-1.5 block whitespace-pre-line text-[0.82rem] leading-relaxed text-mist transition-colors hover:text-gold">
                                        {{ $value }}
                                    </a>
                                @else
                                    <p class="mt-1.5 whitespace-pre-line text-[0.82rem] leading-relaxed text-mist">
                                        {{ $value }}
                                    </p>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach

                @if (count($socialLinks))
                    <div class="flex items-start gap-3.5">
                        <span class="lp-badge lp-badge-md">
                            <x-landing.icon name="sparkle" class="size-4" />
                        </span>

                        <div>
                            @if ($socialLabel)
                                <p class="text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-gold">
                                    {{ $socialLabel }}
                                </p>
                            @endif

                            <ul class="mt-2.5 flex flex-wrap items-center gap-2.5">
                                @foreach ($socialLinks as $link)
                                    @php $platform = $link['platform'] ?? 'globe'; @endphp

                                    <li>
                                        <a
                                            href="{{ $link['url'] ?? '#' }}"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="lp-badge size-9 transition-colors hover:border-gold hover:bg-gold/20"
                                            aria-label="{{ ucfirst(str_replace('-', ' ', $platform)) }}"
                                        >
                                            <x-landing.icon :name="$platform" class="size-4" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </section>
@endif
