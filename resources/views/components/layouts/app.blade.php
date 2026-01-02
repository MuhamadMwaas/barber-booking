<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    {{-- ============================= --}}
    {{-- Basic Meta --}}
    {{-- ============================= --}}
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">


    @stack('meta')


    <title>{{ config('app.name') }}</title>


    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-50 text-gray-900">

        {{ $slot }}





</body>
</html>
