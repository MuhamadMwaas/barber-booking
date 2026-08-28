{{--
    Shell for the public marketing site (landing page + /app download page).

    Everything visitor-facing comes from $landing (App\Services\Landing\LandingContent),
    which resolves the per-locale maps stored in `landing_sections`. The document
    language and direction follow the locale that SetLocaleFromSession picked.

    Every variable below is supplied by LandingController::viewData(), not
    declared here — see the note there for why.

    Expects:
      $landing                          LandingContent
      $languages                        active languages, for the switcher
      $brandName $brandSuffix $brandLabel $logo $favicon
      $seoTitle $seoDescription $seoKeywords $seoImage
--}}
@php
    $locale    = $landing->locale();
    $direction = $landing->direction();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">

    <title>@yield('title', $seoTitle)</title>

    @if ($seoDescription)
        <meta name="description" content="{{ $seoDescription }}">
    @endif

    @if ($seoKeywords)
        <meta name="keywords" content="{{ $seoKeywords }}">
    @endif

    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="icon" href="{{ $favicon }}">

    {{-- Open Graph / Twitter, so shared links render as a card. --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $brandLabel }}">
    <meta property="og:title" content="@yield('title', $seoTitle)">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:locale" content="{{ $locale }}">
    <meta name="twitter:card" content="summary_large_image">

    {{--
        Alternate-language links. The switcher is a session write rather than a
        per-locale URL, so these point at the switch route: crawlers follow it,
        get the page in that language, and the visitor keeps a shareable link.
    --}}
    @foreach ($languages as $language)
        <link rel="alternate" hreflang="{{ $language->code }}" href="{{ route('landing.language', $language->code) }}">
    @endforeach

    {{--
        Fonts are served from Bunny Fonts, not Google Fonts: this site targets
        customers in Germany, where embedding Google Fonts has been held to
        transmit visitor IPs to a third country without consent (LG München I,
        3 O 17493/20). Bunny is GDPR-compliant and API-compatible.
    --}}
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link
        href="https://fonts.bunny.net/css?family=oswald:400,500,600,700|inter:400,500,600,700{{ $direction === 'rtl' ? '|cairo:400,500,600,700' : '' }}"
        rel="stylesheet"
    >

    @vite(['resources/css/landing.css', 'resources/js/landing.js'])

    @stack('head')
</head>

<body class="lp-body">

    {{-- Skip link — the navbar has a lot of tab stops before the content. --}}
    <a href="#lp-main"
       class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:start-4 focus:z-[100] focus:rounded-md focus:bg-gold focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-ink">
        {{ __('landing.skip_to_content') }}
    </a>

    @include('landing.partials.navbar')

    <main id="lp-main">
        @yield('content')
    </main>

    @include('landing.partials.footer')

</body>
</html>
