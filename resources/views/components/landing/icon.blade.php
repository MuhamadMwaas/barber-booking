{{--
    Inline SVG icon.

    Usage: <x-landing.icon name="scissors" class="size-6" />
    Unknown names render nothing, so a content row referencing an icon that was
    later removed degrades to no icon instead of breaking the layout.
--}}
@props([
    'name' => null,
    'class' => 'size-5',
])

@if ($name)
    {!! \App\Support\LandingIcons::svg($name, $class) !!}
@endif
