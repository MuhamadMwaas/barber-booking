<x-filament-panels::page>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <div x-data="salonScheduleManager(@entangle('branchId'))" class="space-y-6">
        {{-- اختيار الفرع --}}
        <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-950/5 p-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="p-3 rounded-xl bg-primary-100">
                    <x-heroicon-o-building-storefront class="w-6 h-6 text-primary-600" />
                </div>
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">
                        {{ __('salon_schedule.select_branch') }}
                    </h2>
                    <p class="text-sm text-gray-500">
                        {{ __('salon_schedule.select_branch_description') }}
                    </p>
                </div>
            </div>

            <div class="max-w-md">
                <select
                    x-model="selectedBranchId"
                    @change="loadSchedule()"
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">{{ __('salon_schedule.choose_branch') }}</option>
                    @foreach(\App\Models\Branch::all() as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- جدول المواعيد --}}
        <div x-show="selectedBranchId" x-cloak>
            <div class="bg-white rounded-2xl shadow-sm ring-1 ring-gray-950/5 p-6">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div class="p-3 rounded-xl bg-emerald-100">
                            <x-heroicon-o-calendar-days class="w-6 h-6 text-emerald-600" />
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">
                                {{ __('salon_schedule.weekly_schedule') }}
                            </h2>
                            <p class="text-sm text-gray-500">
                                {{ __('salon_schedule.manage_opening_hours') }}
                            </p>
                        </div>
                    </div>

                    <button
                        @click="saveSchedule()"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition"
                    >
                        <x-heroicon-o-check class="w-5 h-5" />
                        {{ __('salon_schedule.save_schedule') }}
                    </button>
                </div>

                {{-- أيام الأسبوع --}}
                <div class="space-y-4">
                    <template x-for="(day, index) in days" :key="index">
                        <div class="border border-gray-200 rounded-xl p-4 hover:border-primary-300 transition">
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
                                {{-- اسم اليوم --}}
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-gray-100">
                                        <x-heroicon-o-calendar class="w-5 h-5 text-gray-600" />
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-900" x-text="day.name"></p>
                                        <p class="text-xs text-gray-500" x-text="day.is_open ? '{{ __('salon_schedule.open') }}' : '{{ __('salon_schedule.closed') }}'"></p>
                                    </div>
                                </div>

                                {{-- Toggle فتح/إغلاق --}}
                                <div class="flex items-center gap-2">
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            x-model="day.is_open"
                                            class="sr-only peer"
                                        >
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary-600"></div>
                                    </label>
                                    <span class="text-sm text-gray-600" x-text="day.is_open ? '{{ __('salon_schedule.open') }}' : '{{ __('salon_schedule.closed') }}'"></span>
                                </div>

                                {{-- وقت الفتح --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('salon_schedule.open_time') }}
                                    </label>
                                    <input
                                        type="time"
                                        x-model="day.open_time"
                                        :disabled="!day.is_open"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                </div>

                                {{-- وقت الإغلاق --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('salon_schedule.close_time') }}
                                    </label>
                                    <input
                                        type="time"
                                        x-model="day.close_time"
                                        :disabled="!day.is_open"
                                        class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100 disabled:cursor-not-allowed"
                                    >
                                </div>

                                {{-- ساعات العمل --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        {{ __('salon_schedule.working_hours') }}
                                    </label>
                                    <div class="text-sm font-medium text-emerald-600 bg-emerald-50 px-3 py-2 rounded-lg">
                                        <span x-text="day.is_open ? calculateHours(day.open_time, day.close_time) : '{{ __('salon_schedule.closed') }}'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- ملخص --}}
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-gradient-to-br from-primary-50 to-primary-100 rounded-xl p-4">
                        <p class="text-xs text-primary-600 font-medium mb-1">{{ __('salon_schedule.total_weekly_hours') }}</p>
                        <p class="text-2xl font-bold text-primary-900" x-text="calculateTotalWeeklyHours()"></p>
                    </div>

                    <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 rounded-xl p-4">
                        <p class="text-xs text-emerald-600 font-medium mb-1">{{ __('salon_schedule.open_days_count') }}</p>
                        <p class="text-2xl font-bold text-emerald-900" x-text="countOpenDays() + ' {{ __('salon_schedule.days_unit') }}'"></p>
                    </div>

                    <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl p-4">
                        <p class="text-xs text-amber-600 font-medium mb-1">{{ __('salon_schedule.average_daily_hours') }}</p>
                        <p class="text-2xl font-bold text-amber-900" x-text="calculateAverageDailyHours()"></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function salonScheduleManager(branchId) {
            return {
                selectedBranchId: branchId || '',
                days: [],

                init() {
                    this.initializeDays();
                    if (this.selectedBranchId) {
                        this.loadSchedule();
                    }
                },

                initializeDays() {
                    const dayNames = @json(App\Filament\Schemas\SalonScheduleForm::getLocalizedDays());
                    this.days = Object.entries(dayNames).map(([index, name]) => ({
                        index: parseInt(index),
                        name: name,
                        is_open: false,
                        open_time: '09:00',
                        close_time: '21:00'
                    }));
                },

                async loadSchedule() {
                    if (!this.selectedBranchId) return;

                    try {
                        const response = await fetch(`/admin/api/salon-schedules/${this.selectedBranchId}`);
                        if (response.ok) {
                            const data = await response.json();
                            this.updateDaysFromData(data);
                        }
                    } catch (error) {
                        console.error('Error loading schedule:', error);
                    }
                },

                updateDaysFromData(data) {
                    if (data.days) {
                        Object.entries(data.days).forEach(([dayIndex, dayData]) => {
                            const day = this.days.find(d => d.index === parseInt(dayIndex));
                            if (day) {
                                day.is_open = dayData.is_open || false;
                                day.open_time = dayData.open_time ? dayData.open_time.substring(0, 5) : '09:00';
                                day.close_time = dayData.close_time ? dayData.close_time.substring(0, 5) : '21:00';
                            }
                        });
                    }
                },

                async saveSchedule() {
                    if (!this.selectedBranchId) {
                        alert('{{ __('salon_schedule.please_select_branch') }}');
                        return;
                    }

                    const scheduleData = {
                        branch_id: this.selectedBranchId,
                        days: {}
                    };

                    this.days.forEach(day => {
                        scheduleData.days[day.index] = {
                            is_open: day.is_open,
                            open_time: day.open_time + ':00',
                            close_time: day.close_time + ':00'
                        };
                    });

                    try {
                        const response = await fetch(`/admin/api/salon-schedules/${this.selectedBranchId}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(scheduleData)
                        });

                        if (response.ok) {
                            // Show success notification using Filament
                            window.dispatchEvent(new CustomEvent('notify', {
                                detail: {
                                    type: 'success',
                                    message: '{{ __('salon_schedule.schedule_saved_successfully') }}'
                                }
                            }));
                        } else {
                            throw new Error('Save failed');
                        }
                    } catch (error) {
                        console.error('Error saving schedule:', error);
                        alert('{{ __('salon_schedule.save_error') }}');
                    }
                },

                calculateHours(open, close) {
                    if (!open || !close) return '-';

                    const [openHour, openMin] = open.split(':').map(Number);
                    const [closeHour, closeMin] = close.split(':').map(Number);

                    let minutes = (closeHour * 60 + closeMin) - (openHour * 60 + openMin);
                    if (minutes < 0) minutes += 24 * 60;

                    const hours = Math.floor(minutes / 60);
                    const mins = minutes % 60;

                    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
                },

                calculateTotalWeeklyHours() {
                    let totalMinutes = 0;
                    this.days.forEach(day => {
                        if (day.is_open && day.open_time && day.close_time) {
                            const [openHour, openMin] = day.open_time.split(':').map(Number);
                            const [closeHour, closeMin] = day.close_time.split(':').map(Number);
                            let minutes = (closeHour * 60 + closeMin) - (openHour * 60 + openMin);
                            if (minutes < 0) minutes += 24 * 60;
                            totalMinutes += minutes;
                        }
                    });

                    const hours = Math.floor(totalMinutes / 60);
                    const mins = totalMinutes % 60;
                    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
                },

                countOpenDays() {
                    return this.days.filter(day => day.is_open).length;
                },

                calculateAverageDailyHours() {
                    const openDays = this.countOpenDays();
                    if (openDays === 0) return '0h';

                    let totalMinutes = 0;
                    this.days.forEach(day => {
                        if (day.is_open && day.open_time && day.close_time) {
                            const [openHour, openMin] = day.open_time.split(':').map(Number);
                            const [closeHour, closeMin] = day.close_time.split(':').map(Number);
                            let minutes = (closeHour * 60 + closeMin) - (openHour * 60 + openMin);
                            if (minutes < 0) minutes += 24 * 60;
                            totalMinutes += minutes;
                        }
                    });

                    const avgMinutes = Math.floor(totalMinutes / openDays);
                    const hours = Math.floor(avgMinutes / 60);
                    const mins = avgMinutes % 60;
                    return mins > 0 ? `${hours}h ${mins}m` : `${hours}h`;
                }
            }
        }
    </script>
</x-filament-panels::page>
