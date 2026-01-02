{{-- resources/views/components/seo/meta.blade.php --}}

@php
    $metaTitle = $meta['title'] ?? null;
    $metaDescription = $meta['description'] ?? null;
@endphp

@if($metaTitle)
    <title>{{ $metaTitle }}</title>
    <meta property="og:title" content="{{ $metaTitle }}">
@endif

@if($metaDescription)
    <meta name="description" content="{{ $metaDescription }}">
    <meta property="og:description" content="{{ $metaDescription }}">
@endif
