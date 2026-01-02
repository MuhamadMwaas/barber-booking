{{-- resources/views/pages/privacy.blade.php --}}

<x-layouts.app>
    {{-- Component مسؤول عن <head> --}}
    <x-seo.meta :meta="$meta" />

    <div class="container mx-auto py-10">
        <h1 class="text-3xl font-bold mb-6">
            {{ $title }}
        </h1>

        <article class="prose max-w-none">
            {!! $content !!}
        </article>
    </div>
</x-layouts.app>
