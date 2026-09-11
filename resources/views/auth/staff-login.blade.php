{{--
    Staff Dashboard sign-in.

    Deliberately a plain Blade form and not a Livewire component: it is the one
    page that must work when the dashboard's Livewire runtime does not. The tabs
    all share one Alpine/Livewire runtime, so a component that throws on one page
    can freeze the others — and the page you reach for when the dashboard is
    misbehaving cannot be a page that shares that fate.

    Rendered by StaffAuthController@showLogin. Errors arrive as a `email` field
    error: bad credentials, a disabled account, a non-staff account and a
    throttled address all surface in the same place.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('staff_auth.title') }} — {{ config('app.name') }}</title>
    <link rel="icon" href="/image/logo.png">
    @vite(['resources/css/app.css'])
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-gray-100 min-h-screen flex flex-col items-center justify-center px-4 py-10">

    <main class="w-full max-w-sm">

        <div class="flex flex-col items-center mb-6">
            <img src="/image/logo.png" alt="{{ config('app.name') }}" class="h-16 w-auto mb-3">
            <h1 class="text-lg font-bold text-gray-800 tracking-tight">{{ config('app.name') }}</h1>
            <p class="text-sm text-gray-500 mt-1">{{ __('staff_auth.subtitle') }}</p>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">

            {{-- One block for every refusal: wrong password, disabled account,
                 non-staff account, rate limit. They all arrive on `email`. --}}
            @if ($errors->any())
                <div class="mb-4 rounded-lg bg-rose-50 border border-rose-200 px-3 py-2.5">
                    <ul class="text-sm text-rose-700 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2.5">
                    <p class="text-sm text-amber-800">{{ session('status') }}</p>
                </div>
            @endif

            <form method="POST" action="{{ route('staff.dashboard.login.attempt') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        {{ __('staff_auth.email') }}
                    </label>
                    <input id="email" name="email" type="email" required autofocus
                        autocomplete="username" value="{{ old('email') }}" dir="ltr"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-none transition">
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1.5">
                        {{ __('staff_auth.password') }}
                    </label>
                    <input id="password" name="password" type="password" required
                        autocomplete="current-password" dir="ltr"
                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm text-gray-800 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-none transition">
                </div>

                <label class="flex items-center gap-2 text-sm text-gray-600 select-none cursor-pointer">
                    <input type="checkbox" name="remember" value="1"
                        class="rounded border-gray-300 text-amber-500 focus:ring-amber-400">
                    {{ __('staff_auth.remember') }}
                </label>

                <button type="submit"
                    class="w-full rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold py-2.5 transition">
                    {{ __('staff_auth.submit') }}
                </button>
            </form>
        </div>

        {{-- Guests can switch language before signing in — the /language route is
             registered outside the dashboard gate for exactly this. --}}
        <div class="flex justify-center gap-1 mt-5">
            @foreach (['en' => 'English', 'ar' => 'العربية', 'de' => 'Deutsch'] as $code => $label)
                <a href="{{ route('staff.dashboard.language', $code) }}"
                    class="px-3 py-1.5 text-xs rounded-lg transition {{ app()->getLocale() === $code ? 'bg-white text-amber-600 font-semibold border border-gray-200' : 'text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <p class="text-center text-xs text-gray-400 mt-4">{{ __('staff_auth.help') }}</p>
    </main>

</body>

</html>
