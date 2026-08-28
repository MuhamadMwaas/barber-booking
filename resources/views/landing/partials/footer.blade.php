{{--
    Footer: brand column, two link columns, newsletter, and the legal bottom bar.

    Newsletter note — the project has no subscriber storage, so the form only
    renders when an external action URL (Mailchimp, Brevo, CleverReach …) has
    been configured in the admin panel. Without one the column falls back to a
    mailto button, because rendering an input that silently discards an address
    would be worse than rendering none.
--}}
@php
    $footerEnabled = $landing->enabled('footer');

    $navTitle    = $landing->text('footer.nav_title');
    $navLinks    = $landing->links('footer.nav_links');
    $svcTitle    = $landing->text('footer.services_title');
    $svcLinks    = $landing->links('footer.services_links');

    $newsletterOn      = $landing->bool('footer.newsletter_enabled', true);
    $newsletterAction  = $landing->text('footer.newsletter_action_url');
    $newsletterMailto  = $landing->text('footer.newsletter_email');

    $legalLinks = $landing->items('footer.legal_links');
@endphp

@if ($footerEnabled)
    <footer class="relative border-t border-gold/12 bg-ink-soft">
        <div class="lp-container py-14 lg:py-16">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-[1.3fr_1fr_1fr_1.4fr] lg:gap-12">

                {{-- Brand --}}
                <div>
                    <img
                        src="{{ $logo }}"
                        alt="{{ $brandLabel }}"
                        class="h-14 w-auto"
                        loading="lazy" width="112" height="112"
                    >

                    @if ($tagline = $landing->text('footer.tagline', $landing->text('brand.tagline')))
                        <p class="mt-5 text-[0.82rem] leading-relaxed text-ash">{{ $tagline }}</p>
                    @endif

                    @if ($closing = $landing->text('footer.closing'))
                        <p class="mt-2 text-[0.82rem] leading-relaxed text-ash">{{ $closing }}</p>
                    @endif
                </div>

                {{-- Navigation links --}}
                @if (count($navLinks))
                    <nav aria-label="{{ $navTitle ?: __('landing.footer_navigation') }}">
                        <h2 class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-gold">
                            {{ $navTitle }}
                        </h2>
                        <ul class="mt-5 space-y-2.5">
                            @foreach ($navLinks as $link)
                                <li>
                                    <a href="{{ $landing->linkUrl($link['url'] ?? null) }}"
                                       class="text-[0.82rem] text-ash transition-colors hover:text-gold">
                                        {{ $landing->t($link['label'] ?? null) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                {{-- Services links --}}
                @if (count($svcLinks))
                    <nav aria-label="{{ $svcTitle ?: __('landing.footer_services') }}">
                        <h2 class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-gold">
                            {{ $svcTitle }}
                        </h2>
                        <ul class="mt-5 space-y-2.5">
                            @foreach ($svcLinks as $link)
                                <li>
                                    <a href="{{ $landing->linkUrl($link['url'] ?? '#services') }}"
                                       class="text-[0.82rem] text-ash transition-colors hover:text-gold">
                                        {{ $landing->t($link['label'] ?? null) }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                @endif

                {{-- Newsletter --}}
                @if ($newsletterOn && ($newsletterAction || $newsletterMailto))
                    <div>
                        <h2 class="text-[0.68rem] font-semibold uppercase tracking-[0.22em] text-gold">
                            {{ $landing->text('footer.newsletter_title') }}
                        </h2>

                        @if ($description = $landing->text('footer.newsletter_description'))
                            <p class="mt-5 text-[0.82rem] leading-relaxed text-ash">{{ $description }}</p>
                        @endif

                        @if ($newsletterAction)
                            {{-- Posts straight to the mailing-list provider; nothing
                                 touches this application, so there is no consent
                                 record to keep here. --}}
                            <form
                                action="{{ $newsletterAction }}"
                                method="POST"
                                target="_blank"
                                rel="noopener"
                                class="mt-4 flex items-stretch gap-2"
                            >
                                <label for="lp-newsletter-email" class="sr-only">
                                    {{ $landing->text('footer.newsletter_placeholder', __('landing.email_address')) }}
                                </label>

                                <input
                                    id="lp-newsletter-email"
                                    type="email"
                                    name="{{ $landing->text('footer.newsletter_field_name') ?: 'EMAIL' }}"
                                    required
                                    autocomplete="email"
                                    class="lp-input"
                                    placeholder="{{ $landing->text('footer.newsletter_placeholder', __('landing.email_address')) }}"
                                >

                                <button
                                    type="submit"
                                    class="lp-btn lp-btn-gold shrink-0 px-4 py-0"
                                    aria-label="{{ __('landing.subscribe') }}"
                                >
                                    <x-landing.icon name="scissors" class="size-4" />
                                </button>
                            </form>
                        @else
                            <a href="mailto:{{ $newsletterMailto }}" class="lp-btn lp-btn-outline lp-btn-sm mt-4">
                                <x-landing.icon name="mail" class="size-4" />
                                {{ $landing->text('footer.newsletter_button_label', __('landing.subscribe')) }}
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="border-t border-gold/10">
            <div class="lp-container flex flex-col items-center justify-between gap-3 py-5 sm:flex-row">
                <p class="text-[0.72rem] text-ash">
                    {{ $landing->text('footer.copyright', '© ' . date('Y') . ' ' . $brandLabel) }}
                </p>

                @if (count($legalLinks))
                    <ul class="flex items-center gap-4">
                        @foreach ($legalLinks as $link)
                            <li>
                                <a href="{{ $landing->linkUrl($link['url'] ?? null) }}"
                                   class="text-[0.72rem] text-ash transition-colors hover:text-gold">
                                    {{ $landing->t($link['label'] ?? null) }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </footer>
@endif
