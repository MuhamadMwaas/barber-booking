{{-- Image Line Type --}}
@php
    $imagePath = $properties['image_path'] ?? null;
    $width = $properties['width'] ?? 80;
    $height = $properties['height'] ?? 80;
    $alignment = $properties['alignment'] ?? 'center';
    $marginTop = $properties['margin_top'] ?? 0;
    $marginBottom = $properties['margin_bottom'] ?? 5;

    // Get image URL
    $imageUrl = null;
    if ($imagePath) {
        $imageUrl = \Storage::disk('public')->url($imagePath);
    } elseif ($template->getCompanyInfo('logo_path')) {
        $imageUrl = \Storage::disk('public')->url($template->getCompanyInfo('logo_path'));
    }
@endphp

@if($imageUrl)
<div class="line-item image-line text-{{ $alignment }}" style="margin-top: {{ $marginTop }}px; margin-bottom: {{ $marginBottom }}px;">
    <img src="{{ $imageUrl }}"
         alt="Logo"
         style="width: {{ $width }}px; height: {{ $height }}px; object-fit: contain;">
</div>
@endif
