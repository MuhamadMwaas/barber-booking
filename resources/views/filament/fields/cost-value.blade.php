

<div>
    <div class="text-sm font-medium text-gray-600 mb-1">
        {{ $label }}
    </div>

    <strong style="font-size: {{ $size }}; {{ $color ? "color: $color;" : "" }}">
        {{ $currency }} {{ number_format($value, 2) }}
    </strong>
</div>
