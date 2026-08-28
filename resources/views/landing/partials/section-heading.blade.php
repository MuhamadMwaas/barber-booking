{{--
    The eyebrow / title / gold rule / lead block that opens every section.

    Extracted because the design repeats it verbatim four times; changing the
    rhythm here changes it everywhere.

    Props:
      $eyebrow      string|null
      $title        string|null
      $description  string|null
      $centered     bool         centre-aligned (services, gallery) vs start-aligned
--}}
@php
    $centered ??= false;
    $description ??= null;
@endphp

@if (!empty($eyebrow) || !empty($title) || !empty($description))
    <div @class([
        'lp-reveal',
        'mx-auto max-w-2xl text-center' => $centered,
        'max-w-xl' => ! $centered,
    ])>
        @if (!empty($eyebrow))
            <p class="lp-eyebrow">{{ $eyebrow }}</p>
        @endif

        @if (!empty($title))
            <h2 class="lp-title mt-4">{{ $title }}</h2>
        @endif

        <div @class(['lp-rule', 'mx-auto' => $centered])></div>

        @if (!empty($description))
            <p class="lp-lead mt-5 whitespace-pre-line">{{ $description }}</p>
        @endif
    </div>
@endif
