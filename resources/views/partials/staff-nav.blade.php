{{--
    Staff Navigation Header — shared between StaffDashboard and CustomerLookup.
    Requires: $active ('calendar' | 'customers'), $activeLanguages (array)
--}}
<header class="bg-white border-b border-gray-200 flex items-center justify-between px-4 py-2 flex-shrink-0">
    <div class="flex items-center space-x-6">
        <h1 class="text-lg font-bold text-gray-800 tracking-tight">{{ config('app.name') }}</h1>
        <nav class="flex space-x-1">
            <a href="/dashboard" wire:navigate
                class="px-4 py-2 text-sm font-medium transition-colors {{ ($active ?? '') === 'calendar' ? 'text-amber-600 border-b-2 border-amber-500' : 'text-gray-500 hover:text-gray-700' }}">
                {{ __('dashboard.calendar') }}
            </a>
            <a href="{{ route('staff.dashboard.customers') }}" wire:navigate
                class="px-4 py-2 text-sm font-medium transition-colors {{ ($active ?? '') === 'customers' ? 'text-amber-600 border-b-2 border-amber-500' : 'text-gray-500 hover:text-gray-700' }}">
                {{ __('dashboard.customers') }}
            </a>
            <a href="/admin"
                class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 transition-colors">
                {{ __('dashboard.admin') }}
            </a>
        </nav>
    </div>
    <div class="flex items-center space-x-4">
        {{-- Language Switcher --}}
        <div class="relative" x-data="{ languageOpen: false }">
            <button @click="languageOpen = !languageOpen"
                class="flex items-center space-x-2 rounded-lg px-3 py-2 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.5 21m3.548-6.5A18.021 18.021 0 0017.5 21M12 11a9 9 0 100-18 9 9 0 000 18zm0 0c2.485 0 4.5 4.03 4.5 9s-2.015 9-4.5 9-4.5-4.03-4.5-9 2.015-9 4.5-9z">
                    </path>
                </svg>
                <span class="font-medium uppercase">{{ app()->getLocale() }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>
            <div x-show="languageOpen" x-cloak @click.outside="languageOpen = false" x-transition
                class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-52 rounded-lg border bg-white py-1 shadow-xl z-50">
                @foreach ($activeLanguages as $language)
                    <a href="{{ url('/dashboard/language/' . $language['code']) }}"
                        class="flex items-center justify-between px-3 py-2 text-sm hover:bg-gray-50 {{ app()->getLocale() === $language['code'] ? 'font-semibold text-amber-600' : 'text-gray-700' }}">
                        <span>{{ $language['native_name'] ?: $language['name'] }}</span>
                        <span class="text-xs uppercase text-gray-400">{{ $language['code'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
        {{-- Notifications --}}
        <div class="relative" x-data="{ notifOpen: false }">
            <button @click="notifOpen = !notifOpen"
                class="relative p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9">
                    </path>
                </svg>
            </button>
            <div x-show="notifOpen" @click.outside="notifOpen = false" x-transition
                class="absolute right-0 mt-2 w-72 bg-white rounded-lg shadow-xl border z-50 p-4">
                <h3 class="font-semibold text-sm text-gray-700 mb-2">{{ __('dashboard.notifications') }}</h3>
                <p class="text-sm text-gray-400">{{ __('dashboard.no_notifications') }}</p>
            </div>
        </div>
        {{-- Avatar --}}
        <div class="flex items-center space-x-2">
            <div class="w-8 h-8 bg-amber-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                {{ substr(auth()->user()->first_name ?? 'S', 0, 1) }}
            </div>
        </div>
    </div>
</header>
