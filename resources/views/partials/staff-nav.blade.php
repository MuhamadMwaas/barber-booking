{{--
    Staff Navigation Header — shared between StaffDashboard and CustomerLookup.
    Requires: $active ('calendar' | 'customers'), $activeLanguages (array)
    Optional: $attendanceState (array) — only the StaffDashboard provides it, so
              the check-in/out controls render only there. The avatar card and
              logout render everywhere.
--}}
@php
    $navUser = auth()->user();
    $isProvider = $navUser && method_exists($navUser, 'isProvider') ? $navUser->isProvider() : false;
    $att = $attendanceState ?? null;
    $attStatus = $att['status'] ?? null;
@endphp
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
    <div class="flex items-center space-x-3">
        {{-- Attendance check-in / check-out + history (providers only, on the dashboard) --}}
        @if ($isProvider && $att)
            @if ($attStatus === 'open')
                <button type="button" wire:click="openCheckOutModal" wire:loading.attr="disabled"
                    class="flex items-center space-x-1.5 rounded-lg bg-rose-50 px-3 py-1.5 text-sm font-medium text-rose-600 border border-rose-200 hover:bg-rose-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span>{{ __('dashboard.attendance.check_out') }}</span>
                    @if (!empty($att['since']))
                        <span class="text-[11px] text-rose-400">· {{ $att['since'] }}</span>
                    @endif
                </button>
            @else
                <button type="button" wire:click="openCheckInModal" wire:loading.attr="disabled"
                    class="flex items-center space-x-1.5 rounded-lg bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-600 border border-emerald-200 hover:bg-emerald-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span>{{ __('dashboard.attendance.check_in') }}</span>
                </button>
            @endif
            {{-- Attendance history (last 30 sessions) --}}
            <button type="button" wire:click="openAttendanceHistoryModal"
                title="{{ __('dashboard.attendance.history_title') }}"
                class="p-2 text-gray-500 hover:text-amber-600 rounded-lg hover:bg-gray-100">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </button>
        @endif

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
        {{-- Avatar + profile card --}}
        <div class="relative" x-data="{ cardOpen: false }">
            <button @click="cardOpen = !cardOpen"
                class="w-8 h-8 bg-amber-500 rounded-full flex items-center justify-center text-white text-sm font-semibold overflow-hidden focus:outline-none focus:ring-2 focus:ring-amber-300">
                @if ($navUser?->profile_image_url)
                    <img src="{{ $navUser->profile_image_url }}" alt="" class="w-full h-full object-cover">
                @else
                    {{ substr($navUser->first_name ?? 'S', 0, 1) }}
                @endif
            </button>
            <div x-show="cardOpen" x-cloak @click.outside="cardOpen = false" x-transition
                class="absolute {{ app()->getLocale() === 'ar' ? 'left-0' : 'right-0' }} mt-2 w-64 rounded-xl border bg-white shadow-xl z-50 overflow-hidden">
                {{-- Identity --}}
                <div class="flex items-center gap-3 p-4 border-b border-gray-100">
                    <div class="w-11 h-11 bg-amber-500 rounded-full flex items-center justify-center text-white text-base font-semibold overflow-hidden flex-shrink-0">
                        @if ($navUser?->profile_image_url)
                            <img src="{{ $navUser->profile_image_url }}" alt="" class="w-full h-full object-cover">
                        @else
                            {{ substr($navUser->first_name ?? 'S', 0, 1) }}
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $navUser?->full_name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ $navUser?->email }}</p>
                    </div>
                </div>

                {{-- Today's attendance status (providers only) --}}
                @if ($isProvider && $att)
                    <div class="px-4 py-3 border-b border-gray-100">
                        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wider mb-1">
                            {{ __('dashboard.attendance.today_status') }}</p>
                        @if ($attStatus === 'open')
                            <p class="text-sm text-emerald-600 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                {{ __('dashboard.attendance.present_since', ['time' => $att['since'] ?? '']) }}
                            </p>
                        @elseif ($attStatus === 'closed')
                            <p class="text-sm text-gray-600 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                {{ __('dashboard.attendance.checked_out_at', ['time' => $att['last_out'] ?? '']) }}
                            </p>
                        @else
                            <p class="text-sm text-rose-500 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                                {{ __('dashboard.attendance.not_present') }}
                            </p>
                        @endif
                    </div>
                @endif

                {{-- Logout --}}
                <form method="POST" action="{{ route('filament.admin.auth.logout') }}" class="p-2">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-600 rounded-lg hover:bg-gray-50 hover:text-rose-600 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        {{ __('dashboard.attendance.logout') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
