<div class="h-screen flex flex-col" x-data="dashboardApp()"
    wire:poll.5s
    x-on:booking-saved.window="showBookingModal = false; bookingSaving = false"
    x-on:booking-error.window="bookingSaving = false"
    x-on:timeoff-saved.window="showTimeOffModal = false; timeOffSaving = false">
    {{-- Top Navigation (shared partial) --}}
    @include('partials.staff-nav', ['active' => 'calendar', 'attendanceState' => $attendanceState])

    {{-- ===================== Attendance: Check-in confirmation ===================== --}}
    @if ($showCheckInModal)
        <div class="fixed inset-0 modal-overlay z-[80] flex items-center justify-center p-4" wire:click.self="closeCheckInModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm" @click.stop>
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <span class="w-9 h-9 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </span>
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.attendance.check_in_confirm_title') }}</h3>
                </div>
                <div class="p-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2">
                        <span class="text-gray-500">{{ __('dashboard.attendance.current_time') }}</span>
                        <span class="font-semibold text-emerald-700">{{ $checkInPreview['now'] ?? '' }}</span>
                    </div>
                    <p class="text-xs text-gray-400 text-center">{{ $checkInPreview['now_date'] ?? '' }}</p>
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
                        <span class="text-gray-500">{{ __('dashboard.attendance.last_checkout') }}</span>
                        @if (!empty($checkInPreview['last_out']))
                            <span class="font-medium text-gray-700">{{ $checkInPreview['last_out'] }}</span>
                        @else
                            <span class="text-gray-400">{{ __('dashboard.attendance.no_previous') }}</span>
                        @endif
                    </div>
                    @if (!empty($checkInPreview['last_out_human']))
                        <p class="text-xs text-gray-400 text-center">{{ $checkInPreview['last_out_human'] }}</p>
                    @endif
                </div>
                <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex justify-end gap-2">
                    <button wire:click="closeCheckInModal" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">{{ __('dashboard.attendance.cancel') }}</button>
                    <button wire:click="confirmCheckIn" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white text-sm font-medium rounded-lg">{{ __('dashboard.attendance.confirm_check_in') }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== Attendance: Check-out confirmation ===================== --}}
    @if ($showCheckOutModal)
        <div class="fixed inset-0 modal-overlay z-[80] flex items-center justify-center p-4" wire:click.self="closeCheckOutModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm" @click.stop>
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <span class="w-9 h-9 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </span>
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.attendance.check_out_confirm_title') }}</h3>
                </div>
                <div class="p-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
                        <span class="text-gray-500">{{ __('dashboard.attendance.last_checkin') }}</span>
                        <span class="font-medium text-gray-700">{{ $checkOutPreview['check_in'] ?? '' }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
                        <span class="text-gray-500">{{ __('dashboard.attendance.current_time') }}</span>
                        <span class="font-medium text-gray-700">{{ $checkOutPreview['now'] ?? '' }}</span>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-rose-50 px-3 py-2">
                        <span class="text-rose-500 font-medium">{{ __('dashboard.attendance.shift_duration') }}</span>
                        <span class="font-bold text-rose-700">{{ $checkOutPreview['duration'] ?? '' }}</span>
                    </div>
                </div>
                <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex justify-end gap-2">
                    <button wire:click="closeCheckOutModal" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">{{ __('dashboard.attendance.cancel') }}</button>
                    <button wire:click="confirmCheckOut" wire:loading.attr="disabled"
                        class="px-4 py-2 bg-rose-500 hover:bg-rose-600 text-white text-sm font-medium rounded-lg">{{ __('dashboard.attendance.confirm_check_out') }}</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== Attendance: History (last 30 sessions) ===================== --}}
    @if ($showAttendanceHistoryModal)
        <div class="fixed inset-0 modal-overlay z-[80] flex items-center justify-center p-4" wire:click.self="closeAttendanceHistoryModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg flex flex-col max-h-[80vh]" @click.stop>
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.attendance.history_title') }}</h3>
                    <button wire:click="closeAttendanceHistoryModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="overflow-y-auto p-4">
                    @if (count($attendanceHistory) > 0)
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs text-gray-400 uppercase tracking-wider border-b border-gray-100">
                                    <th class="py-2 pr-2">{{ __('dashboard.attendance.col_date') }}</th>
                                    <th class="py-2 px-2">{{ __('dashboard.attendance.col_in') }}</th>
                                    <th class="py-2 px-2">{{ __('dashboard.attendance.col_out') }}</th>
                                    <th class="py-2 pl-2 text-right">{{ __('dashboard.attendance.col_duration') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attendanceHistory as $row)
                                    <tr class="border-b border-gray-50">
                                        <td class="py-2 pr-2 text-gray-700">
                                            <span class="font-medium">{{ $row['date'] }}</span>
                                            <span class="text-xs text-gray-400">({{ $row['day'] }})</span>
                                        </td>
                                        <td class="py-2 px-2 text-emerald-600 font-medium">{{ $row['in'] }}</td>
                                        <td class="py-2 px-2">
                                            @if ($row['open'])
                                                <span class="text-[11px] font-semibold text-amber-600 bg-amber-100 rounded px-1.5 py-0.5">{{ __('dashboard.attendance.still_open') }}</span>
                                            @else
                                                <span class="text-rose-600 font-medium">{{ $row['out'] }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2 pl-2 text-right text-gray-600">{{ $row['duration'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-center text-sm text-gray-400 py-8">{{ __('dashboard.attendance.no_history') }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="flex flex-1 overflow-hidden">
        {{-- Sidebar --}}
        <aside class="w-72 bg-white border-r border-gray-200 flex flex-col flex-shrink-0 overflow-y-auto">
            {{-- Calendar --}}
            <div class="p-3 border-b border-gray-100">
                <div class="flex items-center justify-between mb-3">
                    <button wire:click="previousMonth" class="p-1 hover:bg-gray-100 rounded text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7">
                            </path>
                        </svg>
                    </button>
                    <span class="text-sm font-semibold text-gray-700">
                        {{ __('dashboard.months.' . $calendarMonth) }} {{ $calendarYear }}
                    </span>
                    <button wire:click="nextMonth" class="p-1 hover:bg-gray-100 rounded text-gray-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7">
                            </path>
                        </svg>
                    </button>
                </div>
                <div class="grid grid-cols-7 gap-0 text-center mb-1">
                    @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day)
                        <div class="text-xs font-medium text-gray-400 py-1">{{ __('dashboard.days.' . $day) }}</div>
                    @endforeach
                </div>
                @php
                    $firstDay = \Carbon\Carbon::create($calendarYear, $calendarMonth, 1);
                    $daysInMonth = $firstDay->daysInMonth;
                    $startDay = $firstDay->dayOfWeekIso - 1;
                    $today = \Carbon\Carbon::today()->format('Y-m-d');
                @endphp
                <div class="grid grid-cols-7 gap-0 text-center">
                    @for ($i = 0; $i < $startDay; $i++)
                        <div class="py-1"></div>
                    @endfor
                    @for ($d = 1; $d <= $daysInMonth; $d++)
                        @php
                            $dateStr = sprintf('%04d-%02d-%02d', $calendarYear, $calendarMonth, $d);
                            $isToday = $dateStr === $today;
                            $isSelected = $dateStr === $selectedDate;
                            $count = $calendarCounts[$dateStr] ?? 0;
                        @endphp
                        <button wire:click="selectDate('{{ $dateStr }}')"
                            class="calendar-day relative py-1 text-xs rounded-md {{ $isSelected ? 'selected' : '' }} {{ $isToday ? 'today' : '' }} hover:bg-gray-100">
                            <span
                                class="{{ $isToday && !$isSelected ? 'text-amber-600 font-bold' : '' }}">{{ $d }}</span>
                            @if ($count > 0)
                                <span
                                    class="block text-[9px] leading-none {{ $isSelected ? 'text-amber-800' : 'text-gray-400' }}">{{ $count }}</span>
                            @endif
                        </button>
                    @endfor
                </div>
                <button wire:click="goToToday"
                    class="w-full mt-2 text-xs text-amber-600 hover:text-amber-700 font-medium py-1 rounded hover:bg-amber-50">
                    {{ __('dashboard.today') }}
                </button>
            </div>

            {{-- Providers --}}
            <div class="p-3 border-b border-gray-100 flex-1 overflow-y-auto">
                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">
                    {{ __('dashboard.team') }}</h3>
                <div class="space-y-1">
                    @foreach ($allProviders as $provider)
                        @php
                            $isProviderSelectable = $provider['is_work_day'] && !$provider['has_day_off'];
                            $isProviderSelected = in_array($provider['id'], $selectedProviderIds, true);
                        @endphp
                        <label
                            class="provider-check flex items-center space-x-2 px-2 py-1.5 rounded-md {{ $isProviderSelectable ? 'cursor-pointer hover:bg-gray-50' : 'cursor-not-allowed bg-gray-50 opacity-60' }} {{ $isProviderSelectable && !$isProviderSelected ? 'opacity-50' : '' }}"
                            @if (!$isProviderSelectable)
                                style="background-image: repeating-linear-gradient(-45deg, rgba(156, 163, 175, 0.14), rgba(156, 163, 175, 0.14) 8px, transparent 8px, transparent 16px);"
                            @endif>
                            <input type="checkbox" @if ($isProviderSelectable) wire:click="toggleProvider({{ $provider['id'] }})" @endif
                                {{ $isProviderSelected ? 'checked' : '' }} {{ $isProviderSelectable ? '' : 'disabled' }}
                                class="w-4 h-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                            <div class="flex-1 min-w-0">
                                <span
                                    class="block truncate text-sm {{ $isProviderSelectable ? 'text-gray-700' : 'text-gray-400 line-through decoration-gray-300' }}">{{ $provider['name'] }}</span>
                                @if ($provider['has_day_off'])
                                    <span
                                        class="text-[10px] text-red-500 font-medium">{{ __('dashboard.on_leave') }}</span>
                                @elseif(!$provider['is_work_day'])
                                    <span
                                        class="text-[10px] text-gray-400 font-medium">{{ __('dashboard.not_working') }}</span>
                                @else
                                    <span class="text-[10px] text-gray-400">{{ $provider['booking_count'] }}
                                        {{ __('dashboard.bookings') }}</span>
                                @endif
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- Bulletin Board (messages) --}}
            <div class="p-3 border-b border-gray-100 flex-shrink-0 bg-amber-50/40"
                x-data="{
                    messagesOpen: (localStorage.getItem('dashboard-messages-open') ?? '1') === '1',
                    toggle() { this.messagesOpen = !this.messagesOpen; localStorage.setItem('dashboard-messages-open', this.messagesOpen ? '1' : '0'); }
                }">
                <button type="button" @click="toggle()"
                    class="w-full text-xs font-semibold text-amber-700 uppercase tracking-wider mb-2 flex items-center justify-between hover:text-amber-800">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 8h10M7 12h6m-6 8l-3 1V5a2 2 0 012-2h12a2 2 0 012 2v9a2 2 0 01-2 2H8l-1 4z"></path>
                        </svg>
                        {{ __('dashboard.messages.title') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        @if (count($dashboardMessages) > 0)
                            <span class="text-[10px] font-bold text-amber-600 bg-amber-100 rounded-full px-1.5 py-0.5">{{ count($dashboardMessages) }}</span>
                        @endif
                        <svg class="w-3.5 h-3.5 transition-transform" :class="messagesOpen ? '' : '-rotate-90'"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </span>
                </button>

                <div x-show="messagesOpen" x-collapse x-cloak>
                {{-- Messages list --}}
                <div class="space-y-1.5 max-h-56 overflow-y-auto mb-2 pr-0.5">
                    @forelse ($dashboardMessages as $message)
                        <div wire:key="msg-{{ $message['id'] }}"
                            class="group relative rounded-lg p-2 text-sm border {{ $message['is_pinned'] ? 'bg-amber-100/70 border-amber-300' : 'bg-white border-gray-200' }}">
                            <div class="flex items-start gap-2">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1">
                                        <span class="text-xs font-semibold text-gray-800 truncate">{{ $message['author_name'] }}</span>
                                        @if ($message['is_pinned'])
                                            <span class="text-[9px] font-bold text-amber-700 bg-amber-200 rounded px-1 leading-tight">{{ __('dashboard.messages.admin_badge') }}</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-700 whitespace-pre-wrap break-words mt-0.5">{{ $message['body'] }}</p>
                                    <span class="text-[10px] text-gray-400">{{ $message['created_human'] }}</span>
                                </div>
                                @if ($message['can_delete'])
                                    <button type="button"
                                        wire:click="deleteMessage({{ $message['id'] }})"
                                        wire:confirm="{{ __('dashboard.messages.delete_confirm') }}"
                                        title="{{ __('dashboard.messages.delete') }}"
                                        class="flex-shrink-0 text-gray-300 hover:text-red-500 transition opacity-0 group-hover:opacity-100">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-2">{{ __('dashboard.messages.empty') }}</p>
                    @endforelse
                </div>

                {{-- Composer (only if allowed to post) --}}
                @if ($this->dashCan('post_message'))
                <div x-data="{ autoGrow(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 88) + 'px'; } }"
                    x-effect="$wire.newMessageBody === '' && $refs.composer && autoGrow($refs.composer)"
                    class="rounded-lg border border-amber-200 bg-white px-2 py-1 focus-within:border-amber-400 focus-within:ring-1 focus-within:ring-amber-400">
                    <div class="flex items-end gap-1.5">
                        <textarea x-ref="composer" wire:model="newMessageBody" rows="1" maxlength="1000"
                            x-init="autoGrow($el)" x-on:input="autoGrow($el)"
                            x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); $wire.addMessage(); }"
                            placeholder="{{ __('dashboard.messages.placeholder') }}"
                            class="flex-1 text-xs border-0 p-1 leading-snug resize-none focus:ring-0 max-h-[88px]"></textarea>
                        <button type="button" wire:click="addMessage"
                            title="{{ __('dashboard.messages.send') }}"
                            class="flex-shrink-0 w-7 h-7 mb-0.5 flex items-center justify-center bg-amber-500 hover:bg-amber-600 text-white rounded-md transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-1 border-t border-gray-100 mt-0.5 pt-0.5">
                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <select wire:model="newMessageExpiry"
                            class="text-[10px] text-gray-500 border-0 bg-transparent p-0 pr-5 focus:ring-0 cursor-pointer">
                            <option value="never">{{ __('dashboard.messages.expiry_never') }}</option>
                            <option value="end_of_day">{{ __('dashboard.messages.expiry_end_of_day') }}</option>
                            <option value="in_24h">{{ __('dashboard.messages.expiry_in_24h') }}</option>
                        </select>
                    </div>
                </div>
                @endif
                </div>
            </div>

            {{-- Action Buttons --}}
            <div class="p-3 space-y-2 flex-shrink-0">
                @if ($this->dashCan('create_booking'))
                    <button @click="openBookingModalLocal()"
                        class="w-full py-2 px-3 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition flex items-center justify-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4">
                            </path>
                        </svg>
                        <span>{{ __('dashboard.add_booking') }}</span>
                    </button>
                @endif
                @if ($this->dashCan('manage_timeoff'))
                    <button @click="openTimeOffModalLocal()"
                        class="w-full py-2 px-3 bg-white hover:bg-gray-50 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 transition flex items-center justify-center space-x-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        <span>{{ __('dashboard.add_time_off') }}</span>
                    </button>
                @endif
            </div>
        </aside>

        {{-- Main Timeline Area --}}
        <main class="flex-1 flex flex-col overflow-hidden bg-gray-50">
            {{-- Date Header --}}
            <div class="bg-white border-b px-4 py-2 flex items-center justify-between flex-shrink-0">
                <div class="flex items-center space-x-3">
                    <h2 class="text-sm font-semibold text-gray-700">
                        {{ \Carbon\Carbon::parse($selectedDate)->format('l, d M Y') }}
                    </h2>
                    @if ($selectedDate !== $today)
                        <button wire:click="goToToday"
                            class="px-2 py-1 text-xs text-amber-600 hover:text-amber-700 font-medium rounded hover:bg-amber-50">
                            {{ __('dashboard.today') }}
                        </button>
                    @endif
                    @if ($selectedDate === $today)
                        <span
                            class="px-2 py-0.5 bg-amber-100 text-amber-700 text-xs rounded-full font-medium">{{ __('dashboard.today') }}</span>
                    @endif
                    {{-- My bookings filter (providers who can also see the team) --}}
                    @if ($this->isCurrentUserProvider() && $this->dashCan('view_team'))
                        <button type="button" wire:click="$toggle('onlyMine')"
                            class="flex items-center gap-1.5 rounded-lg border px-3 py-1 text-xs font-medium transition {{ $onlyMine ? 'bg-amber-500 border-amber-500 text-white shadow-sm' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            {{ __('dashboard.my_bookings') }}
                        </button>
                    @endif
                </div>
                <div class="flex items-center space-x-2">
                    <div class="relative" x-data="{ scaleMenuOpen: false }">
                        <button @click="scaleMenuOpen = !scaleMenuOpen"
                            class="flex items-center space-x-2 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-800">
                            <span>{{ __('dashboard.timeline.scale') }}</span>
                            <span x-text="timelineScaleLabel()"></span>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div x-show="scaleMenuOpen" x-cloak @click.outside="scaleMenuOpen = false" x-transition
                            class="absolute right-0 z-40 mt-2 w-44 rounded-lg border border-gray-200 bg-white p-1 shadow-lg">
                            <template x-for="option in timelineScaleOptions" :key="option.value">
                                <button type="button" @click="setTimelineScale(option.value); scaleMenuOpen = false"
                                    class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-xs text-gray-600 hover:bg-amber-50 hover:text-amber-700"
                                    :class="timelineScale === option.value ? 'bg-amber-50 text-amber-700' : ''">
                                    <span x-text="option.label"></span>
                                    <span x-show="timelineScale === option.value"
                                        class="text-amber-600">&#10003;</span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <span class="text-xs text-gray-400" wire:loading>
                        <svg class="animate-spin h-4 w-4 text-amber-500 inline" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                    </span>
                    @if (!$timelineData['is_open'])
                        <span
                            class="px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full font-medium">{{ __('dashboard.day_off') }}</span>
                    @else
                        <span class="text-xs text-gray-500">{{ $timelineData['start_time'] }} -
                            {{ $timelineData['end_time'] }}</span>
                    @endif
                </div>
            </div>

            {{-- Attendance reminder banner — providers, on a scheduled work day,
                 when not checked in. Evaluated from the page-load snapshot only
                 (no polling), and dismissible for the session. --}}
            @if ($this->isCurrentUserProvider() && ($attendanceState['status'] ?? null) === 'none' && ($attendanceState['is_work_day'] ?? false))
                <div x-data="{ show: true }" x-show="show" x-cloak
                    class="flex items-center justify-between gap-3 bg-rose-50 border-b border-rose-200 px-4 py-2 text-sm text-rose-700 flex-shrink-0">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-medium">{{ __('dashboard.attendance.not_checked_in_today') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="openCheckInModal" wire:loading.attr="disabled"
                            class="rounded-md bg-rose-600 px-3 py-1 text-xs font-semibold text-white hover:bg-rose-700 transition">
                            {{ __('dashboard.attendance.check_in_now') }}
                        </button>
                        <button type="button" @click="show = false"
                            class="text-rose-400 hover:text-rose-600" title="{{ __('dashboard.attendance.dismiss') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            @endif

            {{-- Timeline --}}
            @if ($timelineData['is_open'] && count($timelineData['providers']) > 0)
                <div
                    class="relative flex-1 overflow-auto"
                    id="timeline-container"
                    @scroll.passive="onTimelineContainerScroll(); scheduleLinkedLineRedraw()"
                >
                    <div
                        x-show="dragging"
                        x-cloak
                        class="drag-selection pointer-events-none fixed z-[70] box-border"
                        :class="{ 'is-timeoff': isDragTimeOff() }"
                        :style="dragSelectionOverlayStyle()"
                    ></div>

                    {{--
                        Linked-group connector lines — direct DOM manipulation.
                        Alpine x-for inside SVG evaluates bindings before scope is ready → crash.
                        Fix: write SVG children as innerHTML from drawLinkedLines() JS instead.
                    --}}
                    <svg id="linked-lines-svg"
                         class="pointer-events-none fixed inset-0 z-[60]"
                         x-effect="void timelineScale; scheduleLinkedLineRedraw()">
                    </svg>
                    <div class="flex min-h-full">
                        {{-- Time Labels Column: flex-col حتى يطابق رأس h-10 + الجدول أعمدة الموظفين --}}
                        <div class="flex w-16 flex-shrink-0 flex-col bg-white border-r border-gray-200 sticky left-0 z-10">
                            <div class="sticky top-0 z-20 h-10 flex-shrink-0 border-b border-gray-200 bg-white"></div>
                            @php
                                $start = \Carbon\Carbon::parse($timelineData['start_time']);
                                $end = \Carbon\Carbon::parse($timelineData['end_time']);
                                $totalMinutes = $start->diffInMinutes($end);
                            @endphp
                            <div class="relative flex-shrink-0" :style="timelineColumnStyle({{ $totalMinutes }})">
                                <template x-for="minute in timelineLabelMinutes({{ $totalMinutes }})"
                                    :key="`label-${minute}`">
                                    <div
                                        class="pointer-events-none absolute inset-x-0 flex justify-start"
                                        :style="timelineLabelTickStyle(minute)"
                                    >
                                        {{-- نفس إحداثي border-top لخلية الشبكة؛ -translate-y-1/2 يضع منتصف النص على الخط --}}
                                        <span
                                            class="inline-block -translate-y-1/2 transform px-2 text-[10px] font-medium leading-none text-gray-500"
                                            x-text="formatTimelineMinute(minute, @js($timelineData['start_time']))"
                                        ></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Provider Columns --}}
                        <div class="flex flex-1">
                            @foreach ($timelineData['providers'] as $pIndex => $provider)
                                <div class="relative flex min-w-[180px] flex-1 flex-col border-r-2 border-gray-200 last:border-r-0">
                                    {{-- Provider Header --}}
                                    <div
                                        class="sticky top-0 z-10 flex h-10 flex-shrink-0 items-center justify-center border-b border-gray-200 bg-white px-2">
                                        <span
                                            class="text-xs font-semibold text-gray-700 truncate">{{ $provider['name'] }}</span>
                                    </div>

                                    {{-- Timeline Grid --}}
                                    <div
                                        class="relative flex-shrink-0 select-none"
                                        :style="timelineColumnStyle({{ $totalMinutes }})"
                                        data-provider-id="{{ $provider['id'] }}"
                                        @mousedown="startDrag($event, {{ $provider['id'] }})"
                                    >
                                        {{-- Grid Lines --}}
                                        <template x-for="minute in timelineGridMinutes({{ $totalMinutes }})"
                                            :key="'p{{ $provider['id'] }}-grid-' + minute">
                                            <div class="absolute left-0 right-0" :class="timelineGridLineClass(minute)"
                                                :style="timelineGridLineStyle(minute)"></div>
                                        </template>

                                        {{-- Current Time Indicator --}}
                                        @if ($selectedDate === $today)
                                            @php
                                                $nowMinutes = \Carbon\Carbon::now()->diffInMinutes($start);
                                            @endphp
                                            @if ($nowMinutes >= 0 && $nowMinutes <= $totalMinutes)
                                                <div class="absolute left-0 right-0 z-30 pointer-events-none"
                                                    :style="timelineMarkerStyle({{ $nowMinutes }})">
                                                    <div class="border-t-2 border-red-500 relative">
                                                        <div
                                                            class="absolute -top-1.5 -left-1 w-3 h-3 bg-red-500 rounded-full">
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endif

                                        {{-- Time Off Blocks --}}
                                        @if (isset($timelineData['time_offs'][$provider['id']]))
                                            @foreach ($timelineData['time_offs'][$provider['id']] as $timeOff)
                                                @php
                                                    $toStart = \Carbon\Carbon::parse($timeOff['start_time']);
                                                    $toEnd = \Carbon\Carbon::parse($timeOff['end_time']);
                                                    $toOffsetMinutes = max(0, $start->diffInMinutes($toStart, false));
                                                    $toDurationMinutes = $toStart->diffInMinutes($toEnd);
                                                @endphp
                                                <div class="absolute left-1 right-1 time-off-block bg-gray-100 rounded-md border border-gray-200 z-5 flex items-center justify-center"
                                                    :style="timelineBlockStyle({{ $toOffsetMinutes }},
                                                        {{ $toDurationMinutes }})">
                                                    <span
                                                        class="text-[10px] text-gray-500 font-medium rotate-0">{{ $timeOff['reason'] ?: __('dashboard.on_leave') }}</span>
                                                </div>
                                            @endforeach
                                        @endif

                                        {{-- Appointment Cards --}}
                                        @if (isset($timelineData['appointments'][$provider['id']]))
                                            @foreach ($timelineData['appointments'][$provider['id']] as $apt)
                                                @php
                                                    $aptStart = \Carbon\Carbon::parse($apt['start_time']);
                                                    $aptEnd = \Carbon\Carbon::parse($apt['end_time']);
                                                    $aptOffsetMinutes = max(0, $start->diffInMinutes($aptStart, false));
                                                    $aptDurationMinutes = $aptStart->diffInMinutes($aptEnd);

                                                    $statusClass = match ($apt['status']) {
                                                        0 => 'status-pending border-l-yellow-400',
                                                        1 => 'status-completed border-l-green-500',
                                                        -1, -2 => 'status-cancelled border-l-red-500',
                                                        -3 => 'status-no-show border-l-gray-500',
                                                        default => 'status-pending border-l-yellow-400',
                                                    };
                                                    $bgClass = match ($apt['status']) {
                                                        0 => 'bg-amber-50 hover:bg-amber-100',
                                                        1 => 'bg-green-50 hover:bg-green-100',
                                                        -1, -2 => 'bg-red-50 hover:bg-red-100',
                                                        -3 => 'bg-gray-50 hover:bg-gray-100',
                                                        default => 'bg-white hover:bg-gray-50',
                                                    };
                                                @endphp
                                                <div class="appointment-card absolute left-1 right-1 rounded-md border border-gray-200 {{ $bgClass }} {{ $statusClass }} {{ $apt['was_pushed'] ? 'ring-1 ring-amber-300' : '' }} overflow-hidden z-10 px-1.5 py-1"
                                                    data-appointment-id="{{ $apt['id'] }}"
                                                    data-provider-id="{{ $provider['id'] }}"
                                                    data-linked-root="{{ $apt['linked_group_root_id'] }}"
                                                    data-is-child="{{ $apt['is_child_booking'] ? '1' : '0' }}"
                                                    :style="timelineBlockStyle({{ $aptOffsetMinutes }},
                                                        {{ $aptDurationMinutes }})"
                                                    wire:click="openAppointmentModal({{ $apt['id'] }})"
                                                    title="{{ $apt['services'] }}">
                                                    <div
                                                        class="text-[10px] font-semibold text-gray-800 leading-tight truncate">
                                                        {{ $apt['start_time'] }} - {{ $apt['end_time'] }}
                                                    </div>
                                                    <div x-show="blockVisible({{ $aptDurationMinutes }}, 20)"
                                                        class="text-[10px] text-gray-600 truncate leading-tight">
                                                        {{ $apt['services'] }}</div>
                                                    <div x-show="blockVisible({{ $aptDurationMinutes }}, 35)"
                                                        class="text-[10px] text-gray-500 truncate leading-tight">
                                                        {{ $apt['has_account'] ? '@' : '' }}{{ $apt['customer_name'] }}
                                                    </div>
                                                    <div x-show="blockVisible({{ $aptDurationMinutes }}, 50)"
                                                        class="text-[9px] text-gray-400 truncate">
                                                        #{{ $apt['number'] }}</div>

                                                    {{-- Linked badge: child booking ↔ another appointment (parent or sibling) --}}
                                                    @if ($apt['is_child_booking'])
                                                        <span class="absolute top-0.5 right-0.5 inline-flex items-center rounded-full bg-purple-100 text-purple-700 text-[8px] font-semibold px-1 py-px leading-none"
                                                              title="{{ __('dashboard.linked.child_of') }} #{{ $apt['linked_group_root_id'] }}">
                                                            ↳
                                                        </span>
                                                    @endif

                                                    {{-- Pushed badge --}}
                                                    @if ($apt['was_pushed'])
                                                        <span class="absolute bottom-1.5 right-0.5 inline-flex items-center rounded-full bg-amber-100 text-amber-700 text-[8px] font-semibold px-1 py-px leading-none"
                                                              title="{{ __('dashboard.linked.was_pushed_from', ['time' => $apt['original_start_time'] ?? '']) }}">
                                                            ⚠
                                                        </span>
                                                    @endif

                                                    @if (!empty($apt['service_color_code']))
                                                        <div class="absolute inset-x-0 bottom-0 h-1"
                                                            style="background-color: {{ $apt['service_color_code'] }};">
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @elseif(!$timelineData['is_open'])
                <div class="flex-1 flex items-center justify-center">
                    <div class="text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                            </path>
                        </svg>
                        <p class="mt-2 text-sm text-gray-500">{{ __('dashboard.day_off') }}</p>
                    </div>
                </div>
            @else
                <div class="flex-1 flex items-center justify-center">
                    <p class="text-sm text-gray-500">{{ __('dashboard.timeline.no_providers') }}</p>
                </div>
            @endif
        </main>
    </div>

    {{-- ===== MODALS ===== --}}

    {{-- Create Booking Modal (Alpine-controlled) --}}
    <div x-show="showBookingModal" x-cloak
        class="fixed inset-0 modal-overlay z-50 flex items-center justify-center p-4"
        @click.self="showBookingModal = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.booking_modal.title') }}</h3>
                <button @click="showBookingModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                {{-- Customer Type Toggle --}}
                <div class="flex space-x-2">
                    <button @click="booking.customerType = 'existing'"
                        class="flex-1 py-2 text-sm rounded-lg font-medium"
                        :class="booking.customerType === 'existing' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600'">
                        {{ __('dashboard.booking_modal.existing_customer') }}
                    </button>
                    <button @click="booking.customerType = 'guest'" class="flex-1 py-2 text-sm rounded-lg font-medium"
                        :class="booking.customerType === 'guest' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-600'">
                        {{ __('dashboard.booking_modal.guest_customer') }}
                    </button>
                </div>

                <template x-if="booking.customerType === 'existing'">
                    <div class="relative">
                        <label
                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.select_customer') }}</label>
                        <input type="text" x-model="booking.customerSearch"
                            @focus="booking.customerDropdownOpen = true"
                            placeholder="{{ __('dashboard.search') }}..."
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        <div x-show="booking.customerDropdownOpen && filteredCustomers().length > 0"
                            @click.outside="booking.customerDropdownOpen = false"
                            class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-40 overflow-y-auto">
                            <template x-for="c in filteredCustomers()" :key="c.id">
                                <button
                                    @click="booking.selectedCustomerId = c.id; booking.customerSearch = c.name; booking.customerDropdownOpen = false"
                                    class="w-full text-left px-3 py-2 text-sm hover:bg-amber-50 border-b border-gray-50">
                                    <span x-text="c.name" class="font-medium"></span>
                                    <span x-text="c.phone" class="text-gray-400 ml-2 text-xs"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
                <template x-if="booking.customerType === 'guest'">
                    <div class="space-y-3">
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.customer_name') }}
                                *</label>
                            <input type="text" x-model="booking.guestName"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.customer_phone') }}
                                *</label>
                            <input type="text" x-model="booking.guestPhone"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                        <div>
                            <label
                                class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.customer_email') }}</label>
                            <input type="email" x-model="booking.guestEmail"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        </div>
                    </div>
                </template>

                <hr class="border-gray-100">

                {{-- Services --}}
                <div>
                    <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">
                        {{ __('dashboard.booking_modal.services') }}</h4>
                    <template x-for="(bs, bIndex) in booking.services" :key="bIndex">
                        <div class="bg-gray-50 rounded-lg p-3 mb-3 relative">
                            <button x-show="booking.services.length > 1" @click="booking.services.splice(bIndex, 1)"
                                class="absolute top-2 right-2 text-red-400 hover:text-red-600 text-xs">
                                {{ __('dashboard.booking_modal.remove_service') }}
                            </button>
                            <div class="space-y-3">
                                {{-- Category --}}
                                <div>
                                    <label
                                        class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.select_category') }}</label>
                                    <select x-model="bs.category_id"
                                        @change="bs.service_id = ''; bs.provider_id = ''; bs.duration = 0; bs.price = 0"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                                        <option value="">-- {{ __('dashboard.booking_modal.select_category') }}
                                            --</option>
                                        <template x-for="cat in preloaded.categories" :key="cat.id">
                                            <option :value="cat.id" x-text="cat.name"></option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Service --}}
                                <div x-show="bs.category_id">
                                    <label
                                        class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.select_service') }}</label>
                                    <select x-model="bs.service_id" @change="onServiceChange(bs)"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                                        <option value="">-- {{ __('dashboard.booking_modal.select_service') }}
                                            --</option>
                                        <template x-for="s in servicesForCategory(bs.category_id)"
                                            :key="s.id">
                                            <option :value="s.id"
                                                x-text="s.name + ' (' + s.duration_minutes + ' min - €' + (s.discount_price || s.price) + ')'">
                                            </option>
                                        </template>
                                    </select>
                                </div>
                                {{-- Time & Duration --}}
                                <div x-show="bs.service_id" class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label
                                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.start_time') }}</label>
                                        <input type="time" x-model="bs.start_time" :step="timelineScale * 60"
                                            @change="bs.start_time = snapTime(bs.start_time); bs.provider_id = ''; loadProviders(bIndex)"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                                    </div>
                                    <div>
                                        <label
                                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.duration') }}</label>
                                        <input type="number" x-model.number="bs.duration"
                                            @change="if (bs.service_id && bs.start_time) { bs.provider_id = ''; loadProviders(bIndex); }"
                                            min="5" step="5"
                                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                                    </div>
                                </div>
                                {{-- Available Providers (from server) --}}
                                <div x-show="bs.service_id && bs.start_time">
                                    <label
                                        class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.select_provider') }}</label>
                                    <template x-if="bs._loadingProviders">
                                        <p class="text-xs text-gray-400">{{ __('dashboard.loading') }}...</p>
                                    </template>
                                    <div class="space-y-1" x-show="!bs._loadingProviders">
                                        <template x-for="p in (bs._availableProviders || [])" :key="p.id">
                                            <label
                                                class="flex items-center space-x-2 px-3 py-2 rounded-lg border cursor-pointer hover:bg-amber-50"
                                                :class="bs.provider_id == p.id ? 'border-amber-500 bg-amber-50' :
                                                    'border-gray-200'"
                                                @click="bs.provider_id = p.id">
                                                <span
                                                    class="w-4 h-4 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                                                    :class="bs.provider_id == p.id ? 'border-amber-500' : 'border-gray-300'">
                                                    <span x-show="bs.provider_id == p.id"
                                                        class="w-2 h-2 rounded-full bg-amber-500"></span>
                                                </span>
                                                <span class="text-sm" x-text="p.name"></span>
                                            </label>
                                        </template>
                                        <template
                                            x-if="!bs._loadingProviders && (!bs._availableProviders || bs._availableProviders.length === 0)">
                                            <p class="text-xs text-gray-400">
                                                {{ __('dashboard.booking_modal.no_providers') }}</p>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                    <button @click="addBookingService()"
                        class="text-xs text-amber-600 hover:text-amber-700 font-medium">+
                        {{ __('dashboard.booking_modal.add_service') }}</button>
                </div>

                {{-- Notes --}}
                <div>
                    <label
                        class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.notes') }}</label>
                    <textarea x-model="booking.notes" rows="2"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500"></textarea>
                </div>
            </div>
            <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex justify-end space-x-2">
                <button @click="showBookingModal = false"
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('dashboard.booking_modal.cancel') }}</button>
                <button @click="submitBooking()" :disabled="bookingSaving"
                    class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg">
                    <span x-show="!bookingSaving">{{ __('dashboard.booking_modal.save') }}</span>
                    <span x-show="bookingSaving">...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Appointment Detail Modal --}}
    @if ($showAppointmentModal && $selectedAppointment)
        <div class="fixed inset-0 modal-overlay z-50 flex items-center justify-center p-4"
            wire:click.self="closeAppointmentModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md" @click.stop>
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.appointment_modal.title') }}</h3>
                    <button wire:click="closeAppointmentModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    @php $canAct = $this->canActOnAppointment($selectedAppointment); @endphp
                    {{-- Ownership notice: provider is viewing a booking that is not theirs.
                         All actions still work (subject to the edit_others permission). --}}
                    @if ($this->isCurrentUserProvider() && (int) $selectedAppointment->provider_id !== $this->currentProviderId())
                        <div class="flex items-start gap-2 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                            <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>{{ __('dashboard.not_your_booking') }}</span>
                        </div>
                    @endif
                    @php
                        $statusBadge = match ($selectedAppointment->status->value) {
                            0 => [
                                'wrapper' => 'bg-amber-100 text-amber-800',
                                'dot' => 'bg-amber-500',
                            ],
                            1 => [
                                'wrapper' => 'bg-green-100 text-green-800',
                                'dot' => 'bg-green-500',
                            ],
                            -1, -2 => [
                                'wrapper' => 'bg-red-100 text-red-800',
                                'dot' => 'bg-red-500',
                            ],
                            -3 => [
                                'wrapper' => 'bg-slate-200 text-slate-700',
                                'dot' => 'bg-slate-500',
                            ],
                            default => [
                                'wrapper' => 'bg-gray-100 text-gray-700',
                                'dot' => 'bg-gray-400',
                            ],
                        };

                        $paymentBadge = match ($selectedAppointment->payment_status->value) {
                            0 => [
                                'wrapper' => 'bg-amber-100 text-amber-800',
                                'dot' => 'bg-amber-500',
                            ],
                            1, 2, 3 => [
                                'wrapper' => 'bg-green-100 text-green-800',
                                'dot' => 'bg-green-500',
                            ],
                            4 => [
                                'wrapper' => 'bg-red-100 text-red-800',
                                'dot' => 'bg-red-500',
                            ],
                            5, 6 => [
                                'wrapper' => 'bg-sky-100 text-sky-800',
                                'dot' => 'bg-sky-500',
                            ],
                            default => [
                                'wrapper' => 'bg-gray-100 text-gray-700',
                                'dot' => 'bg-gray-400',
                            ],
                        };
                    @endphp
                    {{-- Appointment Info --}}
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.booking_number') }}</span>
                            <span class="font-medium">#{{ $selectedAppointment->number }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.customer') }}</span>
                            <span class="font-medium">
                                @if ($selectedAppointment->customer_id)
                                    <span class="text-amber-600">@</span>
                                @endif
                                {{ $selectedAppointment->customer?->full_name ?: $selectedAppointment->getRawOriginal('customer_name') ?: 'Guest' }}
                            </span>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-500">{{ __('dashboard.appointment_modal.service') }}</span>
                                <span class="font-medium">{{ $selectedAppointment->services_record->count() }}</span>
                            </div>
                            <div class="space-y-1.5">
                                @foreach ($selectedAppointment->services_record->sortBy('sequence_order') as $serviceRecord)
                                    <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
                                        <span class="min-w-0 truncate font-medium text-gray-700">{{ $serviceRecord->service_name }}</span>
                                        <span class="flex shrink-0 items-center gap-2 text-xs text-gray-500">
                                            <span>{{ $serviceRecord->duration_minutes }} {{ __('dashboard.appointment_modal.minutes_short') }}</span>
                                            <span class="text-gray-300">|</span>
                                            <span>{{ number_format((float) $serviceRecord->price, 2) }} €</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.provider') }}</span>
                            <span class="font-medium">{{ $selectedAppointment->provider?->full_name }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.status') }}</span>
                            <span
                                class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusBadge['wrapper'] }}">
                                <span class="h-2.5 w-2.5 rounded-full {{ $statusBadge['dot'] }}"></span>
                                <span>{{ $selectedAppointment->status->getLabel() }}</span>
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">{{ __('dashboard.appointment_modal.payment_status') }}</span>
                            <span
                                class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold {{ $paymentBadge['wrapper'] }}">
                                <span class="h-2.5 w-2.5 rounded-full {{ $paymentBadge['dot'] }}"></span>
                                <span>{{ $selectedAppointment->payment_status->label() }}</span>
                            </span>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    {{-- Linked group info --}}
                    @if ($selectedAppointment->is_child_booking && $selectedAppointment->parent)
                        <div class="rounded-lg border border-purple-200 bg-purple-50 p-3 text-xs text-purple-800">
                            <div class="font-semibold mb-1">↳ {{ __('dashboard.linked.linked_booking') }}</div>
                            <div>{{ __('dashboard.linked.child_of') }} <span class="font-medium">#{{ $selectedAppointment->parent->number }}</span></div>
                            <div class="mt-1 text-purple-600">{{ __('dashboard.linked.invoice_on_parent') }}</div>
                        </div>
                    @elseif ($selectedAppointment->children->isNotEmpty())
                        <div class="rounded-lg border border-purple-200 bg-purple-50 p-3 text-xs text-purple-800">
                            <div class="font-semibold mb-1">{{ __('dashboard.linked.has_children') }}</div>
                            <ul class="list-disc list-inside space-y-0.5">
                                @foreach ($selectedAppointment->children as $childAppt)
                                    <li>
                                        #{{ $childAppt->number }} —
                                        {{ $childAppt->services_record->pluck('service_name')->implode(', ') }}
                                        ({{ $childAppt->provider?->full_name }})
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Push history --}}
                    @if ($selectedAppointment->was_pushed && $selectedAppointment->original_start_time)
                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
                            <span class="font-semibold">⚠ {{ __('dashboard.linked.was_pushed_label') }}</span>
                            {{ __('dashboard.linked.was_pushed_from', ['time' => $selectedAppointment->original_start_time->format('H:i')]) }}
                        </div>
                    @endif

                    {{-- Customer Notes Accordion --}}
                    <div x-data="{ notesOpen: false }">
                        <button
                            type="button"
                            @click="notesOpen = !notesOpen"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-sm font-medium text-gray-700 transition-colors">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                <span>{{ __('dashboard.appointment_modal.notes') }}</span>
                                @if ($selectedAppointment->notes)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">
                                        {{ __('dashboard.appointment_modal.has_notes') }}
                                    </span>
                                @endif
                            </span>
                            <svg class="w-4 h-4 text-gray-400 transition-transform duration-200"
                                :class="notesOpen ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div
                            x-show="notesOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            class="mt-2">
                            <textarea
                                wire:model="editNotes"
                                rows="3"
                                placeholder="{{ __('dashboard.appointment_modal.notes_placeholder') }}"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:ring-amber-500 focus:border-amber-500 resize-none"></textarea>
                            @if ($canAct && $this->dashCan('edit_notes'))
                                <button
                                    wire:click="updateNotes"
                                    class="mt-1.5 w-full px-3 py-2 bg-amber-50 hover:bg-amber-100 text-amber-700 text-sm font-medium rounded-lg border border-amber-200 transition-colors">
                                    {{ __('dashboard.appointment_modal.save_notes') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Provider Notes Accordion --}}
                    <div x-data="{ providerNotesOpen: false }">
                        <button
                            type="button"
                            @click="providerNotesOpen = !providerNotesOpen"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-sm font-medium text-blue-700 transition-colors">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <span>{{ __('dashboard.appointment_modal.provider_notes') }}</span>
                                @if ($selectedAppointment->provider_notes)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700">
                                        {{ __('dashboard.appointment_modal.has_notes') }}
                                    </span>
                                @endif
                            </span>
                            <svg class="w-4 h-4 text-blue-400 transition-transform duration-200"
                                :class="providerNotesOpen ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div
                            x-show="providerNotesOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            class="mt-2">
                            <textarea
                                wire:model="editProviderNotes"
                                rows="3"
                                placeholder="{{ __('dashboard.appointment_modal.provider_notes_placeholder') }}"
                                class="w-full border border-blue-200 rounded-lg px-3 py-2 text-sm text-gray-700 placeholder-gray-400 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>
                            @if ($canAct && $this->dashCan('edit_notes'))
                                <button
                                    wire:click="updateProviderNotes"
                                    class="mt-1.5 w-full px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-medium rounded-lg border border-blue-200 transition-colors">
                                    {{ __('dashboard.appointment_modal.save_provider_notes') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Colors Used Accordion --}}
                    <div x-data="{
                        colorsOpen: false,
                        newColorId: '',
                        newColorQty: '',
                        addingColor: false,
                        async submitColor() {
                            if (!this.newColorId || !this.newColorQty) return;
                            this.addingColor = true;
                            await $wire.addColorToAppointment(parseInt(this.newColorId), parseFloat(this.newColorQty));
                            this.newColorId = '';
                            this.newColorQty = '';
                            this.addingColor = false;
                        }
                    }"
                    @color-added.window="addingColor = false"
                    @color-removed.window="addingColor = false">
                        <button
                            type="button"
                            @click="colorsOpen = !colorsOpen"
                            class="w-full flex items-center justify-between px-3 py-2 rounded-lg bg-purple-50 hover:bg-purple-100 text-sm font-medium text-purple-700 transition-colors">
                            <span class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                                </svg>
                                <span>{{ __('dashboard.appointment_modal.colors_used') }}</span>
                                @if ($selectedAppointment->colorRecords->isNotEmpty())
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-700">
                                        {{ $selectedAppointment->colorRecords->count() }}
                                    </span>
                                @endif
                            </span>
                            <svg class="w-4 h-4 text-purple-400 transition-transform duration-200"
                                :class="colorsOpen ? 'rotate-180' : ''"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div
                            x-show="colorsOpen"
                            x-transition:enter="transition ease-out duration-150"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-100"
                            x-transition:leave-start="opacity-100 translate-y-0"
                            x-transition:leave-end="opacity-0 -translate-y-1"
                            class="mt-2 space-y-2">

                            {{-- Existing colors list --}}
                            @forelse ($selectedAppointment->colorRecords as $colorRecord)
                                <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-100 rounded-lg">
                                    <span class="w-5 h-5 rounded flex-shrink-0 border border-gray-200"
                                          style="background-color: {{ $colorRecord->color->hex_code ?? '#ccc' }}"></span>
                                    <span class="flex-1 text-sm font-medium text-gray-700">
                                        {{ $colorRecord->color->name ?? '—' }}
                                        @if ($colorRecord->color?->brand)
                                            <span class="text-xs text-gray-400">({{ $colorRecord->color->brand }})</span>
                                        @endif
                                    </span>
                                    <span class="text-sm text-gray-500">
                                        {{ number_format($colorRecord->quantity, 2) }} {{ $colorRecord->color->unit ?? '' }}
                                    </span>
                                    @if ($canAct && $this->dashCan('manage_colors'))
                                        <button
                                            wire:click="removeColorFromAppointment({{ $colorRecord->id }})"
                                            wire:confirm="{{ __('dashboard.appointment_modal.confirm_remove_color') }}"
                                            class="text-red-400 hover:text-red-600 p-1 rounded">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            @empty
                                <p class="text-xs text-gray-400 italic px-1">{{ __('dashboard.appointment_modal.no_colors') }}</p>
                            @endforelse

                            {{-- Add color row --}}
                            <div class="flex gap-2 pt-1" @if (!($canAct && $this->dashCan('manage_colors'))) style="display:none" @endif>
                                <select x-model="newColorId"
                                    class="flex-1 border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-purple-500 focus:border-purple-500">
                                    <option value="">-- {{ __('dashboard.appointment_modal.select_color') }} --</option>
                                    @foreach ($preloadedData['colors'] as $color)
                                        <option value="{{ $color['id'] }}">
                                            {{ $color['display_name'] }} ({{ $color['unit'] }})
                                        </option>
                                    @endforeach
                                </select>
                                <input type="number" x-model="newColorQty"
                                    min="0.01" step="0.01"
                                    placeholder="{{ __('dashboard.appointment_modal.qty') }}"
                                    class="w-20 border border-gray-300 rounded-lg px-2 py-1.5 text-sm focus:ring-purple-500 focus:border-purple-500">
                                <button
                                    @click="submitColor()"
                                    :disabled="!newColorId || !newColorQty || addingColor"
                                    class="px-3 py-1.5 bg-purple-500 hover:bg-purple-600 disabled:opacity-50 text-white text-sm font-medium rounded-lg">
                                    <span x-show="!addingColor">+</span>
                                    <span x-show="addingColor">…</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Edit Time --}}
                    <div>
                        <h4 class="text-xs font-semibold text-gray-500 uppercase mb-2">
                            {{ __('dashboard.appointment_modal.edit_time') }}</h4>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label
                                    class="block text-xs text-gray-500 mb-1">{{ __('dashboard.appointment_modal.new_start_time') }}</label>
                                <input type="time" wire:model.live="editStartTime" :step="timelineScale * 60"
                                    lang="en" dir="ltr"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label
                                    class="block text-xs text-gray-500 mb-1">{{ __('dashboard.appointment_modal.new_end_time') }}</label>
                                <input type="time" wire:model.live="editEndTime" :step="timelineScale * 60"
                                    lang="en" dir="ltr"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div>
                                <label
                                    class="block text-xs text-gray-500 mb-1">{{ __('dashboard.appointment_modal.new_duration') }}</label>
                                <input type="number" wire:model.live="editDuration" min="5" step="5"
                                    lang="en" dir="ltr"
                                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex items-center justify-between flex-wrap gap-2">
                    <div class="flex flex-wrap gap-2">
                        @if (!in_array($selectedAppointment->payment_status->value, [1, 2, 3]) && $selectedAppointment->status->value !== 1)
                            @if ($canAct && $this->dashCan('cancel_appointment'))
                                <button wire:click="cancelAppointment"
                                    wire:confirm="{{ __('dashboard.appointment_modal.confirm_cancel') }}"
                                    class="px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">{{ __('dashboard.appointment_modal.cancel_appointment') }}</button>
                            @endif
                            @if ($canAct && $this->dashCan('delete_appointment'))
                                <button wire:click="deleteAppointment"
                                    wire:confirm="{{ __('dashboard.appointment_modal.confirm_delete') }}"
                                    class="px-3 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg">{{ __('dashboard.appointment_modal.delete') }}</button>
                            @endif
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        {{-- Add Service Button: only when booking can accept new services --}}
                        @if ($selectedAppointment->canAcceptNewService() && $canAct && $this->dashCan('add_service'))
                            <button wire:click="openAddServiceModal({{ $selectedAppointment->id }})"
                                class="px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white text-sm font-medium rounded-lg inline-flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                <span>{{ __('dashboard.add_service.button') }}</span>
                            </button>
                        @endif

                        {{-- Print Invoice Button: only when invoice is paid --}}
                        @if ($selectedAppointment->canPrintInvoice() && $this->dashCan('print_invoice'))
                            <button wire:click="printInvoiceForAppointment({{ $selectedAppointment->id }})"
                                class="px-3 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg inline-flex items-center gap-1"
                                title="{{ $selectedAppointment->is_child_booking ? __('dashboard.print.combined_with_parent') : ($selectedAppointment->is_parent_booking ? __('dashboard.print.combined_invoice') : '') }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                                <span>{{ __('dashboard.print.button') }}</span>
                                @if ($selectedAppointment->is_child_booking || $selectedAppointment->is_parent_booking)
                                    <span class="text-[10px] opacity-80">({{ __('dashboard.print.unified') }})</span>
                                @endif
                            </button>
                        @endif

                        {{-- Print Order Ticket Button: shown for all non-cancelled appointments --}}
                        @if (! in_array($selectedAppointment->status, [\App\Enum\AppointmentStatus::USER_CANCELLED, \App\Enum\AppointmentStatus::ADMIN_CANCELLED], true) && $this->dashCan('print_ticket'))
                            <button wire:click="printAppointmentTicket({{ $selectedAppointment->id }})"
                                class="px-3 py-2 bg-slate-700 hover:bg-slate-800 text-white text-sm font-medium rounded-lg inline-flex items-center gap-1 cursor-pointer transform transition-transform duration-150 ease-out hover:scale-105 hover:shadow-md active:scale-95"
                                title="{{ ($selectedAppointment->is_child_booking || $selectedAppointment->is_parent_booking) ? __('dashboard.print.order_combined_group') : '' }}">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h2a2 2 0 012 2v6m-6 0h6m-7 0H5a2 2 0 01-2-2v-5a2 2 0 012-2h14a2 2 0 012 2v5a2 2 0 01-2 2h-2M7 7V5a2 2 0 012-2h6a2 2 0 012 2v2"></path></svg>
                                <span>{{ __('dashboard.print.order_button') }}</span>
                                @if ($selectedAppointment->is_child_booking || $selectedAppointment->is_parent_booking)
                                    <span class="text-[10px] opacity-80">({{ __('dashboard.print.unified') }})</span>
                                @endif
                            </button>
                        @endif

                        @if ($selectedAppointment->status->value === 0 && $canAct && $this->dashCan('take_payment'))
                            <button wire:click="openPaymentModal({{ $selectedAppointment->id }})"
                                class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg">
                                {{ __('dashboard.appointment_modal.pay') }}
                            </button>
                        @endif
                        @if ($canAct && $this->dashCan('edit_appointment'))
                            <button wire:click="updateAppointment"
                                class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg">
                                {{ __('dashboard.appointment_modal.save_changes') }}
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Payment Modal --}}
    @if ($showPaymentModal && $selectedAppointmentId)
        @php $payApt = $selectedAppointment ?? \App\Models\Appointment::with('services_record')->find($selectedAppointmentId); @endphp
        <div class="fixed inset-0 modal-overlay z-50 flex items-center justify-center p-4"
            wire:click.self="closePaymentModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm" @click.stop>
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.payment_modal.title') }}</h3>
                    <button wire:click="closePaymentModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    @if ($payApt)
                        <div class="text-sm text-gray-500">
                            {{ $payApt->services_record->map(fn($s) => $s->service_name)->implode(', ') }}
                        </div>
                    @endif
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.payment_modal.amount_to_pay') }}</label>
                        <input type="number" wire:model="paymentAmount" step="0.01" min="0"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-lg font-semibold text-center focus:ring-amber-500 focus:border-amber-500">
                        <p class="text-[10px] text-gray-400 mt-1">{{ __('dashboard.payment_modal.discount_note') }}
                        </p>
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-2">{{ __('dashboard.payment_modal.payment_type') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                class="flex items-center justify-center px-4 py-3 rounded-lg border cursor-pointer {{ $paymentType === '2' ? 'border-amber-500 bg-amber-50' : 'border-gray-200' }}">
                                <input type="radio" wire:model="paymentType" value="2" class="sr-only">
                                <span class="text-sm font-medium">{{ __('dashboard.payment_modal.cash') }}</span>
                            </label>
                            <label
                                class="flex items-center justify-center px-4 py-3 rounded-lg border cursor-pointer {{ $paymentType === '3' ? 'border-amber-500 bg-amber-50' : 'border-gray-200' }}">
                                <input type="radio" wire:model="paymentType" value="3" class="sr-only">
                                <span class="text-sm font-medium">{{ __('dashboard.payment_modal.card') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex justify-end space-x-2">
                    <button wire:click="closePaymentModal"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('dashboard.payment_modal.cancel') }}</button>
                    <button wire:click="processPayment"
                        class="px-5 py-2 bg-green-500 hover:bg-green-600 text-white text-sm font-medium rounded-lg"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove
                            wire:target="processPayment">{{ __('dashboard.payment_modal.confirm_pay') }}</span>
                        <span wire:loading wire:target="processPayment">...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Time Off Modal (Alpine-controlled) --}}
    <div x-show="showTimeOffModal" x-cloak
        class="fixed inset-0 modal-overlay z-50 flex items-center justify-center p-4"
        @click.self="showTimeOffModal = false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm" @click.stop>
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.time_off_modal.title') }}</h3>
                <button @click="showTimeOffModal = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <label
                        class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.select_provider') }}</label>
                    <select x-model="timeOff.providerId"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                        <option value="">-- {{ __('dashboard.time_off_modal.select_provider') }} --
                        </option>
                        @foreach ($allProviders as $p)
                            <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label
                        class="block text-xs font-medium text-gray-500 mb-2">{{ __('dashboard.time_off_modal.type') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label
                            class="flex items-center justify-center px-3 py-2 rounded-lg border cursor-pointer"
                            :class="timeOff.type === '1' ? 'border-amber-500 bg-amber-50' : 'border-gray-200'">
                            <input type="radio" x-model="timeOff.type" value="1" class="sr-only">
                            <span
                                class="text-sm font-medium">{{ __('dashboard.time_off_modal.full_day') }}</span>
                        </label>
                        <label
                            class="flex items-center justify-center px-3 py-2 rounded-lg border cursor-pointer"
                            :class="timeOff.type === '0' ? 'border-amber-500 bg-amber-50' : 'border-gray-200'">
                            <input type="radio" x-model="timeOff.type" value="0" class="sr-only">
                            <span class="text-sm font-medium">{{ __('dashboard.time_off_modal.hourly') }}</span>
                        </label>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.start_date') }}</label>
                        <input type="date" x-model="timeOff.startDate"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.end_date') }}</label>
                        <input type="date" x-model="timeOff.endDate"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>
                </div>
                <div x-show="timeOff.type === '0'" class="grid grid-cols-2 gap-3">
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.start_time') }}</label>
                        <input type="time" x-model="timeOff.startTime"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.end_time') }}</label>
                        <input type="time" x-model="timeOff.endTime"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>
                </div>
                @if ($this->reasonLeaves && $this->reasonLeaves->count() > 0)
                    <div>
                        <label
                            class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.time_off_modal.reason') }}</label>
                        <select x-model="timeOff.reasonId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            <option value="">-- {{ __('dashboard.time_off_modal.reason') }} --</option>
                            @foreach ($this->reasonLeaves as $reason)
                                <option value="{{ $reason->id }}">
                                    {{ $reason->getNameIn(app()->getLocale()) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
            <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex justify-end space-x-2">
                <button @click="showTimeOffModal = false"
                    class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('dashboard.time_off_modal.cancel') }}</button>
                <button @click="submitTimeOff()" :disabled="timeOffSaving"
                    class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg">
                    <span x-show="!timeOffSaving">{{ __('dashboard.time_off_modal.save') }}</span>
                    <span x-show="timeOffSaving">...</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ===== Add Service to Existing Booking Modal ===== --}}
    @if ($showAddServiceModal && $addServiceToAppointmentId)
        @php
            $addAnchor = \App\Models\Appointment::with(['provider', 'parent'])->find($addServiceToAppointmentId);
        @endphp
        <div class="fixed inset-0 modal-overlay z-50 flex items-center justify-center p-4"
             wire:click.self="closeAddServiceModal">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.add_service.title') }}</h3>
                        @if ($addAnchor)
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ __('dashboard.add_service.anchor_info', [
                                    'number' => $addAnchor->number,
                                    'start' => $addAnchor->start_time->format('H:i'),
                                    'end' => $addAnchor->end_time->format('H:i'),
                                ]) }}
                            </p>
                        @endif
                    </div>
                    <button wire:click="closeAddServiceModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    {{-- Placement: before / after --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-2 uppercase tracking-wider">
                            {{ __('dashboard.add_service.placement') }}
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex items-center justify-center px-3 py-2 rounded-lg border cursor-pointer"
                                   :class="$wire.addServiceForm.placement === 'before' ? 'border-amber-500 bg-amber-50' : 'border-gray-200'">
                                <input type="radio" wire:model.live="addServiceForm.placement" value="before" class="sr-only" @change="triggerAnalysis()">
                                <span class="text-sm font-medium">{{ __('dashboard.add_service.placement_before') }}</span>
                            </label>
                            <label class="flex items-center justify-center px-3 py-2 rounded-lg border cursor-pointer"
                                   :class="$wire.addServiceForm.placement === 'after' ? 'border-amber-500 bg-amber-50' : 'border-gray-200'">
                                <input type="radio" wire:model.live="addServiceForm.placement" value="after" class="sr-only" @change="triggerAnalysis()">
                                <span class="text-sm font-medium">{{ __('dashboard.add_service.placement_after') }}</span>
                            </label>
                        </div>
                    </div>

                    {{-- Category --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.select_category') }}</label>
                        <select wire:model.live="addServiceForm.category_id"
                                @change="onAddServiceCategoryChange(); $nextTick(() => triggerAnalysis())"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            <option value="">-- {{ __('dashboard.booking_modal.select_category') }} --</option>
                            <template x-for="cat in preloaded.categories" :key="cat.id">
                                <option :value="cat.id" x-text="cat.name"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Service --}}
                    <div x-show="$wire.addServiceForm.category_id">
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.booking_modal.select_service') }}</label>
                        <select wire:model.live="addServiceForm.service_id"
                                @change="onAddServiceChange($event.target.value); $nextTick(() => triggerAnalysis())"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            <option value="">-- {{ __('dashboard.booking_modal.select_service') }} --</option>
                            <template x-for="s in servicesForAddCategory()" :key="s.id">
                                <option :value="s.id" x-text="s.name + ' (' + s.duration_minutes + ' min - €' + (s.discount_price || s.price) + ')'"></option>
                            </template>
                        </select>
                    </div>

                    {{-- Duration --}}
                    <div x-show="$wire.addServiceForm.service_id">
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.add_service.duration_label') }}</label>
                        <input type="number" wire:model.live.debounce.300ms="addServiceForm.duration_minutes" min="5" step="5"
                               @change="triggerAnalysis()"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                    </div>

                    {{-- Provider (defaults to same as anchor; user can change → child mode) --}}
                    <div x-show="$wire.addServiceForm.service_id">
                        <label class="block text-xs font-medium text-gray-500 mb-1">{{ __('dashboard.add_service.provider_label') }}</label>
                        <select wire:model.live="addServiceForm.provider_id"
                                @change="triggerAnalysis()"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-amber-500 focus:border-amber-500">
                            @if ($addAnchor && $addAnchor->provider)
                                <option value="{{ $addAnchor->provider_id }}">{{ $addAnchor->provider->full_name }} ({{ __('dashboard.add_service.same_provider') }})</option>
                            @endif
                            @foreach ($allProviders as $p)
                                @if ($addAnchor && $p['id'] != $addAnchor->provider_id)
                                    <option value="{{ $p['id'] }}">{{ $p['name'] }} ({{ __('dashboard.add_service.different_provider') }})</option>
                                @endif
                            @endforeach
                        </select>
                        <p class="text-[10px] text-gray-400 mt-1">{{ __('dashboard.add_service.provider_hint') }}</p>
                    </div>

                    {{-- Analysis Result Box --}}
                    @php $analysis = $addServiceAnalysis; @endphp
                    @if (!empty($analysis))
                        @if ($analysis['is_possible'] ?? false)
                            @if ($analysis['requires_push'] ?? false)
                                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                    <div class="font-semibold mb-1">⚠ {{ __('dashboard.add_service.requires_push') }}</div>
                                    <div class="text-xs">{{ __('dashboard.add_service.push_count_will', ['count' => count($analysis['push_plan'] ?? [])]) }}</div>
                                    <div class="text-xs mt-1">{{ __('dashboard.add_service.start_at') }}: <span class="font-mono font-semibold">{{ $analysis['suggested_start_time'] ?? '' }}</span></div>
                                </div>
                            @else
                                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
                                    <div class="font-semibold mb-1">✓ {{ __('dashboard.add_service.fits_well') }}</div>
                                    <div class="text-xs">{{ __('dashboard.add_service.start_at') }}: <span class="font-mono font-semibold">{{ $analysis['suggested_start_time'] ?? '' }}</span> → <span class="font-mono font-semibold">{{ $analysis['suggested_end_time'] ?? '' }}</span></div>
                                    @if (($analysis['gap_minutes'] ?? 0) > 0)
                                        <div class="text-xs mt-1">{{ __('dashboard.add_service.gap_info', ['minutes' => $analysis['gap_minutes']]) }}</div>
                                    @endif
                                </div>
                            @endif
                        @else
                            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                                <div class="font-semibold mb-1">✕ {{ __('dashboard.add_service.cannot_fit') }}</div>
                                <div class="text-xs">
                                    @switch($analysis['reason'] ?? '')
                                        @case('insufficient_space')
                                        @case('no_space_before')
                                        @case('exceeds_work_hours')
                                            {{ __('dashboard.add_service.reason_insufficient', ['max' => $analysis['max_duration_available'] ?? 0]) }}
                                            @break
                                        @case('gap_too_large')
                                            {{ __('dashboard.add_service.reason_gap_too_large', ['gap' => $analysis['gap_minutes'] ?? 0]) }}
                                            @break
                                        @case('paid_booking_in_chain')
                                            {{ __('dashboard.add_service.reason_paid_in_chain', ['number' => $analysis['blocking_appointment_number'] ?? '?', 'max' => $analysis['max_duration_available'] ?? 0]) }}
                                            @break
                                        @case('new_provider_busy')
                                            {{ __('dashboard.add_service.reason_provider_busy') }}
                                            @break
                                        @case('provider_not_working')
                                        @case('provider_full_day_off')
                                        @case('provider_time_off_conflict')
                                            {{ __('dashboard.add_service.reason_provider_unavailable') }}
                                            @break
                                        @default
                                            {{ $analysis['reason'] ?? 'unknown' }}
                                    @endswitch
                                </div>
                                @if (!empty($analysis['max_duration_available']))
                                    <button wire:click="applyMaxDuration"
                                            class="mt-2 px-2 py-1 text-xs bg-red-600 text-white rounded hover:bg-red-700">
                                        {{ __('dashboard.add_service.reduce_to_max', ['min' => $analysis['max_duration_available']]) }}
                                    </button>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>

                <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex justify-end space-x-2">
                    <button wire:click="closeAddServiceModal"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('dashboard.add_service.cancel') }}</button>
                    @php
                        $analysisPossible = ($addServiceAnalysis['is_possible'] ?? false) === true;
                        $needsPush = ($addServiceAnalysis['requires_push'] ?? false) === true;
                    @endphp
                    <button wire:click="confirmAddService({{ $needsPush ? 'true' : 'false' }})"
                            @disabled(!$analysisPossible)
                            class="px-5 py-2 bg-amber-500 hover:bg-amber-600 disabled:bg-gray-300 disabled:cursor-not-allowed text-white text-sm font-medium rounded-lg">
                        @if ($needsPush)
                            {{ __('dashboard.add_service.review_push') }}
                        @else
                            {{ __('dashboard.add_service.confirm') }}
                        @endif
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Push Preview Modal ===== --}}
    @if ($showPushPreviewModal && !empty($pushPreviewPlan))
        <div class="fixed inset-0 modal-overlay z-[60] flex items-center justify-center p-4"
             wire:click.self="cancelPushPreview">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto" @click.stop>
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-800">{{ __('dashboard.push_preview.title') }}</h3>
                    <button wire:click="cancelPushPreview" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <p class="text-sm text-gray-700">{{ __('dashboard.push_preview.intro', ['count' => count($pushPreviewPlan)]) }}</p>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-xs font-medium text-gray-500 uppercase tracking-wider">
                                <tr>
                                    <th class="px-3 py-2 text-left">#</th>
                                    <th class="px-3 py-2 text-left">{{ __('dashboard.push_preview.customer') }}</th>
                                    <th class="px-3 py-2 text-left">{{ __('dashboard.push_preview.original') }}</th>
                                    <th class="px-3 py-2 text-left">{{ __('dashboard.push_preview.new') }}</th>
                                    <th class="px-3 py-2 text-left">{{ __('dashboard.push_preview.push_min') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach ($pushPreviewPlan as $item)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-700">#{{ $item['appointment_number'] }}</td>
                                        <td class="px-3 py-2 text-gray-700">
                                            @if (!empty($item['has_customer_account']))<span class="text-amber-600">@</span>@endif
                                            {{ $item['customer_name'] }}
                                        </td>
                                        <td class="px-3 py-2 text-gray-500 line-through">
                                            {{ $item['original_start'] }} → {{ $item['original_end'] }}
                                        </td>
                                        <td class="px-3 py-2 text-red-600 font-semibold">
                                            {{ $item['new_start'] }} → {{ $item['new_end'] }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <span class="inline-block bg-amber-100 text-amber-700 rounded px-1.5 py-0.5 text-xs font-semibold">
                                                +{{ $item['push_minutes'] }}m
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500">{{ __('dashboard.push_preview.notify_note') }}</p>
                </div>
                <div class="px-5 py-3 bg-gray-50 rounded-b-xl flex justify-end space-x-2">
                    <button wire:click="cancelPushPreview"
                            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">{{ __('dashboard.push_preview.cancel') }}</button>
                    <button wire:click="confirmPushAndAddService"
                            class="px-5 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg">
                        {{ __('dashboard.push_preview.confirm') }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    <script>
        function dashboardApp() {
            return {
                dragging: false,
                dragProviderId: null,
                dragStartY: 0,
                dragCurrentY: 0,
                dragStartOffset: 0,
                _dragTimelineEl: null,
                _onDocDragMove: null,
                _onDocDragUp: null,
                _dragLayoutVersion: 0,

                // ---- Linked-group connector lines (parent ↔ children) ----
                linkedLines: [],
                _linkedRedrawHandle: null,

                showBookingModal: false,
                bookingSaving: false,

                showTimeOffModal: false,
                timeOffSaving: false,
                timeOff: {
                    providerId: '',
                    type: '1',
                    startDate: '',
                    endDate: '',
                    startTime: '',
                    endTime: '',
                    reasonId: '',
                },
                timelineScaleStorageKey: 'staff-dashboard-timeline-scale',
                timelineBaseSlotHeight: 45,
                timelineScaleOptions: [{
                        value: 15,
                        label: @js(__('dashboard.timeline.scale_15'))
                    },
                    {
                        value: 30,
                        label: @js(__('dashboard.timeline.scale_30'))
                    },
                    {
                        value: 10,
                        label: @js(__('dashboard.timeline.scale_10'))
                    },
                ],
                timelineScale: 15,
                preloaded: window.__dashboardPreloaded || @json($preloadedData),
                booking: {
                    customerType: 'existing',
                    selectedCustomerId: null,
                    customerSearch: '',
                    customerDropdownOpen: false,
                    guestName: '',
                    guestPhone: '',
                    guestEmail: '',
                    services: [{
                        category_id: '',
                        service_id: '',
                        start_time: '',
                        duration: 0,
                        provider_id: '',
                        price: 0,
                        _availableProviders: [],
                        _loadingProviders: false
                    }],
                    notes: '',
                },

                resetBooking() {
                    this.booking = {
                        customerType: 'existing',
                        selectedCustomerId: null,
                        customerSearch: '',
                        customerDropdownOpen: false,
                        guestName: '',
                        guestPhone: '',
                        guestEmail: '',
                        services: [{
                            category_id: '',
                            service_id: '',
                            start_time: '',
                            duration: 0,
                            provider_id: '',
                            price: 0,
                            _availableProviders: [],
                            _loadingProviders: false
                        }],
                        notes: '',
                    };
                    this.bookingSaving = false;
                },

                resetTimeOff() {
                    this.timeOff = {
                        providerId: '',
                        type: '1',
                        startDate: '',
                        endDate: '',
                        startTime: '',
                        endTime: '',
                        reasonId: '',
                    };
                    this.timeOffSaving = false;
                },

                openTimeOffModalLocal() {
                    this.resetTimeOff();
                    this.timeOff.startDate = this.$wire.selectedDate;
                    this.timeOff.endDate = this.$wire.selectedDate;
                    this.showTimeOffModal = true;
                },

                openTimeOffModalFromTimelineLocal(providerId, startTime, endTime) {
                    this.resetTimeOff();
                    this.timeOff.providerId = String(providerId);
                    this.timeOff.type = '0';
                    this.timeOff.startDate = this.$wire.selectedDate;
                    this.timeOff.endDate = this.$wire.selectedDate;
                    this.timeOff.startTime = startTime;
                    this.timeOff.endTime = endTime;
                    this.showTimeOffModal = true;
                },

                async submitTimeOff() {
                    this.timeOffSaving = true;
                    try {
                        await this.$wire.saveTimeOffFromAlpine({
                            providerId: this.timeOff.providerId,
                            type: this.timeOff.type,
                            startDate: this.timeOff.startDate,
                            endDate: this.timeOff.endDate,
                            startTime: this.timeOff.startTime,
                            endTime: this.timeOff.endTime,
                            reasonId: this.timeOff.reasonId,
                        });
                    } catch (e) {
                        this.timeOffSaving = false;
                    }
                },

                init() {
                    const savedScale = parseInt(window.localStorage.getItem(this.timelineScaleStorageKey), 10);
                    if (this.timelineScaleOptions.some(option => option.value === savedScale)) {
                        this.timelineScale = savedScale;
                    }

                    // Redraw linked-group connector lines after each Livewire update
                    // (timeline data may have changed → cards moved → recalc).
                    // Register the global listeners only ONCE: wire:navigate keeps the JS
                    // runtime alive across pages, so re-registering on every component init
                    // would leak hooks that also fire on OTHER pages (e.g. the Customers
                    // search morph) and run against destroyed component instances.
                    if (!window.__linkedLinesHooked) {
                        window.__linkedLinesHooked = true;
                        if (window.Livewire) {
                            Livewire.hook('morph.updated', () => this.scheduleLinkedLineRedraw());
                        }
                        window.addEventListener('resize', () => this.scheduleLinkedLineRedraw());
                    }
                    this.scheduleLinkedLineRedraw();
                },

                // ---- Linked-group SVG connector lines ----
                scheduleLinkedLineRedraw() {
                    // No-op on any page without the timeline SVG (e.g. the Customers tab
                    // reached via wire:navigate). Guards the global morph/resize hooks from
                    // touching unrelated pages and corrupting their Livewire/Alpine runtime.
                    if (!document.getElementById('linked-lines-svg')) return;
                    // Coalesce rapid calls to a single rAF.
                    if (this._linkedRedrawHandle) return;
                    this._linkedRedrawHandle = requestAnimationFrame(() => {
                        this._linkedRedrawHandle = null;
                        this.drawLinkedLines();
                    });
                },

                drawLinkedLines() {
                    const cards = document.querySelectorAll('.appointment-card[data-linked-root]');
                    const groups = new Map();
                    cards.forEach(el => {
                        const root = el.dataset.linkedRoot;
                        if (!root) return;
                        if (!groups.has(root)) groups.set(root, []);
                        groups.get(root).push(el);
                    });

                    const lines = [];
                    let lineIdx = 0;
                    groups.forEach((items, rootId) => {
                        if (items.length < 2) return;
                        // Sort by start time stored on the card (best-effort: use rect.top instead)
                        items.sort((a, b) => a.getBoundingClientRect().top - b.getBoundingClientRect().top);

                        for (let i = 1; i < items.length; i++) {
                            const a = items[i - 1].getBoundingClientRect();
                            const b = items[i].getBoundingClientRect();
                            // Mid-point on the right edge of the previous card → mid-point on the left edge of the next
                            lines.push({
                                id: `lk-${rootId}-${lineIdx++}`,
                                x1: a.right,
                                y1: a.top + a.height / 2,
                                x2: b.left,
                                y2: b.top + b.height / 2,
                            });
                        }
                    });
                    // Write directly to SVG DOM — avoids Alpine x-for/SVG scope bug
                    const svg = document.getElementById('linked-lines-svg');
                    if (!svg) return;
                    svg.innerHTML = lines.map(l =>
                        `<g>` +
                        `<line x1="${l.x1}" y1="${l.y1}" x2="${l.x2}" y2="${l.y2}" stroke="#a855f7" stroke-width="2" stroke-dasharray="5 3" stroke-linecap="round"/>` +
                        `<circle cx="${l.x1}" cy="${l.y1}" r="3" fill="#a855f7"/>` +
                        `<circle cx="${l.x2}" cy="${l.y2}" r="3" fill="#a855f7"/>` +
                        `</g>`
                    ).join('');
                },

                // ---- Add Service Modal helpers (Alpine ↔ Livewire bridge) ----
                addServiceModalAlpine() { /* placeholder, real method below */ },

                onAddServiceCategoryChange() {
                    // Reset downstream service id when category changes.
                    this.$wire.set('addServiceForm.service_id', null);
                    this.$wire.set('addServiceForm.duration_minutes', 0);
                },

                onAddServiceChange(serviceId = null) {
                    const svcId = serviceId || this.$wire.get('addServiceForm.service_id');
                    const svc = this.preloaded.services.find(s => s.id == svcId);
                    if (svc) {
                        this.$wire.set('addServiceForm.duration_minutes', svc.duration_minutes);
                    } else {
                        this.$wire.set('addServiceForm.duration_minutes', 0);
                    }
                },

                servicesForAddCategory() {
                    const catId = this.$wire.get('addServiceForm.category_id');
                    if (!catId) return [];
                    return this.preloaded.services.filter(s => s.category_id == catId);
                },

                async triggerAnalysis() {
                    // Debounced server roundtrip — analyzeAddServiceGap() returns a snapshot.
                    try {
                        await this.$wire.analyzeAddServiceGap();
                    } catch (e) {
                        // swallow — UI will show stale or empty state
                    }
                },

                setTimelineScale(value) {
                    const normalizedValue = parseInt(value, 10);
                    if (!this.timelineScaleOptions.some(option => option.value === normalizedValue)) {
                        return;
                    }

                    this.timelineScale = normalizedValue;
                    window.localStorage.setItem(this.timelineScaleStorageKey, String(normalizedValue));
                    this.booking.services = this.booking.services.map(service => ({
                        ...service,
                        start_time: service.start_time ? this.snapTime(service.start_time) : service.start_time,
                        provider_id: service.start_time ? '' : service.provider_id,
                        _availableProviders: service.start_time ? [] : (service._availableProviders || []),
                    }));
                },

                timelineScaleLabel() {
                    return this.timelineScaleOptions.find(option => option.value === this.timelineScale)?.label || '';
                },

                pixelsPerMinute() {
                    return this.timelineBaseSlotHeight / this.timelineScale;
                },

                minuteToPixels(minutes) {
                    return minutes * this.pixelsPerMinute();
                },

                timelineColumnStyle(totalMinutes) {
                    return `height: ${this.minuteToPixels(totalMinutes)}px;`;
                },

                /** محاذاة تسمية الوقت مع خط الشبكة (نفس top مثل timelineGridLineStyle) */
                timelineLabelTickStyle(minute) {
                    const y = this.minuteToPixels(minute);
                    return `top:${y}px;`;
                },

                timelineGridLineStyle(minute) {
                    return `top: ${this.minuteToPixels(minute)}px; height: ${this.timelineBaseSlotHeight}px;`;
                },

                timelineMarkerStyle(offsetMinutes) {
                    return `top: ${this.minuteToPixels(offsetMinutes)}px;`;
                },

                timelineBlockStyle(offsetMinutes, durationMinutes) {
                    return `top: ${this.minuteToPixels(offsetMinutes)}px; height: ${this.minuteToPixels(durationMinutes)}px;`;
                },

                blockVisible(durationMinutes, thresholdPixels) {
                    return this.minuteToPixels(durationMinutes) > thresholdPixels;
                },

                timelineGridMinutes(totalMinutes) {
                    return Array.from({
                            length: Math.ceil(totalMinutes / this.timelineScale)
                        }, (_, index) => index * this.timelineScale)
                        .filter(minute => minute < totalMinutes);
                },

                timelineLabelMinutes(totalMinutes) {
                    return Array.from({
                            length: Math.ceil(totalMinutes / this.timelineScale)
                        }, (_, index) => index * this.timelineScale)
                        .filter(minute => minute < totalMinutes);
                },

                timelineGridLineClass(minute) {
                    if (minute % 60 === 0) {
                        return 'timeline-grid-line-hour';
                    }

                    return minute % 30 === 0 ? 'timeline-grid-line' : 'border-t border-gray-100';
                },

                formatTimelineMinute(offsetMinutes, startTime) {
                    const [startHours, startMinutes] = startTime.split(':').map(Number);
                    const totalMinutes = (startHours * 60) + startMinutes + offsetMinutes;
                    const hours = String(Math.floor(totalMinutes / 60)).padStart(2, '0');
                    const minutes = String(totalMinutes % 60).padStart(2, '0');

                    return `${hours}:${minutes}`;
                },

                snapTime(time) {
                    if (!time) {
                        return time;
                    }

                    const [hours, minutes] = time.split(':').map(Number);
                    const totalMinutes = (hours * 60) + minutes;
                    const snappedMinutes = Math.round(totalMinutes / this.timelineScale) * this.timelineScale;
                    const normalizedHours = Math.floor((snappedMinutes % (24 * 60)) / 60);
                    const normalizedMinutes = snappedMinutes % 60;

                    return `${String(normalizedHours).padStart(2, '0')}:${String(normalizedMinutes).padStart(2, '0')}`;
                },

                openBookingModalLocal(providerId = null, startTime = null) {
                    this.resetBooking();
                    if (startTime) {
                        this.booking.services[0].start_time = this.snapTime(startTime);
                    } else {
                        const now = new Date();
                        const currentTime =
                            `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
                        this.booking.services[0].start_time = this.snapTime(currentTime);
                    }
                    if (providerId) {
                        this.booking.services[0]._preselectedProvider = providerId;
                    }
                    this.showBookingModal = true;
                },

                filteredCustomers() {
                    const q = (this.booking.customerSearch || '').toLowerCase().trim();
                    if (!q) return this.preloaded.customers.slice(0, 20);
                    return this.preloaded.customers.filter(c =>
                        (c.name && c.name.toLowerCase().includes(q)) ||
                        (c.phone && c.phone.includes(q)) ||
                        (c.email && c.email.toLowerCase().includes(q))
                    ).slice(0, 20);
                },

                servicesForCategory(categoryId) {
                    if (!categoryId) return [];
                    const cid = parseInt(categoryId);
                    return this.preloaded.services.filter(s => s.category_id === cid);
                },

                onServiceChange(bs) {
                    const service = this.preloaded.services.find(s => s.id == bs.service_id);
                    if (service) {
                        bs.duration = service.duration_minutes;
                        bs.price = service.discount_price || service.price;
                    } else {
                        bs.duration = 0;
                        bs.price = 0;
                    }
                    bs.provider_id = '';
                    bs._availableProviders = [];
                    if (bs.start_time && bs.service_id) {
                        this.loadProvidersForService(bs);
                    }
                },

                addBookingService() {
                    const lastService = this.booking.services[this.booking.services.length - 1];
                    let nextTime = '';
                    if (lastService && lastService.start_time && lastService.duration) {
                        const [h, m] = lastService.start_time.split(':').map(Number);
                        const totalMin = h * 60 + m + (lastService.duration || 30);
                        nextTime = this.snapTime(String(Math.floor(totalMin / 60)).padStart(2, '0') + ':' + String(
                            totalMin % 60).padStart(2, '0'));
                    }
                    this.booking.services.push({
                        category_id: '',
                        service_id: '',
                        start_time: nextTime,
                        duration: 0,
                        provider_id: '',
                        price: 0,
                        _availableProviders: [],
                        _loadingProviders: false
                    });
                },

                async loadProviders(bIndex) {
                    const bs = this.booking.services[bIndex];
                    if (!bs || !bs.service_id || !bs.start_time) return;
                    await this.loadProvidersForService(bs);
                },

                async loadProvidersForService(bs) {
                    if (!bs.service_id || !bs.start_time) return;
                    bs._loadingProviders = true;
                    bs._availableProviders = [];
                    try {
                        const result = await this.$wire.getAvailableProvidersForBooking(
                            parseInt(bs.service_id),
                            bs.start_time,
                            bs.duration || 30
                        );
                        bs._availableProviders = result;
                        if (bs._preselectedProvider && result.some(p => p.id == bs._preselectedProvider)) {
                            bs.provider_id = bs._preselectedProvider;
                            delete bs._preselectedProvider;
                        }
                    } catch (e) {
                        bs._availableProviders = [];
                    }
                    bs._loadingProviders = false;
                },

                async submitBooking() {
                    this.bookingSaving = true;
                    const data = {
                        customerType: this.booking.customerType,
                        selectedCustomerId: this.booking.selectedCustomerId,
                        guestName: this.booking.guestName,
                        guestPhone: this.booking.guestPhone,
                        guestEmail: this.booking.guestEmail,
                        notes: this.booking.notes,
                        services: this.booking.services.map(s => ({
                            category_id: s.category_id,
                            service_id: s.service_id,
                            start_time: s.start_time,
                            duration: s.duration,
                            provider_id: s.provider_id,
                            price: s.price,
                        })),
                    };
                    try {
                        await this.$wire.saveBookingFromAlpine(data);
                        this.showBookingModal = false;
                        this.resetBooking();
                    } catch (e) {
                        this.bookingSaving = false;
                    }
                },

                detachDocumentDragListeners() {
                    if (this._onDocDragMove) {
                        document.removeEventListener('mousemove', this._onDocDragMove);
                        this._onDocDragMove = null;
                    }
                    if (this._onDocDragUp) {
                        document.removeEventListener('mouseup', this._onDocDragUp);
                        this._onDocDragUp = null;
                    }
                },

                onTimelineContainerScroll() {
                    if (this.dragging) {
                        this._dragLayoutVersion++;
                    }
                },

                dragDurationMinutes() {
                    if (!this.dragging || !this._dragTimelineEl) return 0;
                    const diff = Math.abs(this.dragCurrentY - this.dragStartY);
                    const ppm = this.pixelsPerMinute();
                    return diff / ppm;
                },

                isDragTimeOff() {
                    return this.dragDurationMinutes() > 15;
                },

                dragSelectionOverlayStyle() {
                    if (!this.dragging || !this._dragTimelineEl) {
                        return 'display:none;';
                    }
                    void this._dragLayoutVersion;
                    void this.dragStartY;
                    void this.dragCurrentY;
                    const col = this._dragTimelineEl.getBoundingClientRect();
                    const pad = 4;
                    const topRel = Math.min(this.dragStartY, this.dragCurrentY);
                    const h = Math.max(Math.abs(this.dragCurrentY - this.dragStartY), 3);
                    const left = col.left + pad;
                    const top = col.top + topRel;
                    const width = Math.max(col.width - pad * 2, 0);
                    return `position:fixed;left:${left}px;top:${top}px;width:${width}px;height:${h}px;`;
                },

                startDrag(e, providerId) {
                    if (e.target.closest('.appointment-card')) return;
                    if (e.target.closest('.time-off-block')) return;
                    const el = e.currentTarget;
                    if (!el) return;
                    e.preventDefault();
                    this.detachDocumentDragListeners();
                    this._dragTimelineEl = el;
                    const rect = el.getBoundingClientRect();
                    this.dragging = true;
                    this.dragProviderId = Number(providerId);
                    this.dragStartY = e.clientY - rect.top;
                    this.dragCurrentY = this.dragStartY;

                    this._onDocDragMove = (ev) => this.onDocumentDragMove(ev);
                    this._onDocDragUp = () => this.finishDragFromDocument();
                    document.addEventListener('mousemove', this._onDocDragMove, { passive: true });
                    document.addEventListener('mouseup', this._onDocDragUp);
                },

                onDocumentDragMove(e) {
                    if (!this.dragging || !this._dragTimelineEl) return;
                    const rect = this._dragTimelineEl.getBoundingClientRect();
                    let y = e.clientY - rect.top;
                    y = Math.max(0, Math.min(rect.height, y));
                    this.dragCurrentY = y;
                },

                // No-op: kept for backward compat if referenced elsewhere
                _legacyAddServiceModalShim() {},

                finishDragFromDocument() {
                    this.detachDocumentDragListeners();
                    const providerId = this.dragProviderId;
                    const timelineEl = this._dragTimelineEl;
                    this._dragTimelineEl = null;

                    if (!this.dragging || providerId == null || !timelineEl) {
                        this.dragging = false;
                        this.dragProviderId = null;
                        return;
                    }

                    const diff = Math.abs(this.dragCurrentY - this.dragStartY);
                    const isClick = diff < 10;
                    const pixelsPerMinute = this.pixelsPerMinute();
                    const draggedMinutes = diff / pixelsPerMinute;

                    const topY = isClick ? this.dragStartY : Math.min(this.dragStartY, this.dragCurrentY);
                    const minutesFromStart = Math.round(topY / pixelsPerMinute / this.timelineScale) * this.timelineScale;

                    const timelineStart = @json($timelineData['start_time'] ?? '09:00');
                    const [startH, startM] = timelineStart.split(':').map(Number);
                    const totalStartMinutes = startH * 60 + startM + minutesFromStart;
                    const hours = String(Math.floor(totalStartMinutes / 60)).padStart(2, '0');
                    const mins = String(totalStartMinutes % 60).padStart(2, '0');
                    const startTime = hours + ':' + mins;

                    this.dragging = false;
                    this.dragProviderId = null;

                    if (!isClick && draggedMinutes > 15) {
                        // Drag > 15 min → open time off modal
                        const bottomY = Math.max(this.dragStartY, this.dragCurrentY);
                        const endMinutesFromStart = Math.round(bottomY / pixelsPerMinute / this.timelineScale) * this.timelineScale;
                        const totalEndMinutes = startH * 60 + startM + endMinutesFromStart;
                        const endHours = String(Math.floor(totalEndMinutes / 60)).padStart(2, '0');
                        const endMins = String(totalEndMinutes % 60).padStart(2, '0');
                        const endTime = endHours + ':' + endMins;
                        this.openTimeOffModalFromTimelineLocal(providerId, startTime, endTime);
                    } else {
                        // Click or short drag → open booking modal
                        this.openBookingModalLocal(providerId, startTime);
                    }
                },
            }
        }
    </script>
</div>
