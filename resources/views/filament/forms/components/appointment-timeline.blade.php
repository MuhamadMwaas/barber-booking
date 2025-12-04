@php
    use Carbon\Carbon;
    use App\Filament\Resources\Appointments\Schemas\AppointmentForm;

    $date = $date ?? Carbon::now()->format('Y-m-d');
    $services = $services ?? [];
    $selectedProvider = $selectedProvider ?? null;
    $selectedTime = $selectedTime ?? null;
    $serviceDuration = $serviceDuration ?? collect($services)->sum('duration_minutes') ?? 30;

    // Get providers timeline data
    $providers = AppointmentForm::getProvidersTimeline($date, $services);
@endphp
@vite(['resources/css/app.css', 'resources/js/app.js'])

<div
    class="w-full"
    x-data="{
        providers: {{ json_encode($providers) }},
        serviceDuration: {{ $serviceDuration }},
        selectedTime: '{{ $selectedTime ?? '' }}',
        selectedProvider: {{ $selectedProvider ?? 'null' }},
        timeSlots: [],

        init() {
            this.generateAllSlots();

            // Watch for manual changes to start_time from form input
            this.$watch(() => this.$wire.get('data.start_time'), (value) => {
                if (value && value !== this.selectedTime) {
                    this.selectedTime = value;
                }
            });

            // Watch for provider_id changes
            this.$watch(() => this.$wire.get('data.provider_id'), (value) => {
                if (value && value !== this.selectedProvider) {
                    this.selectedProvider = value;
                }
            });

            // Watch for duration changes - this will track edited duration_minutes
            this.$watch(() => this.$wire.get('data.duration_minutes'), (value) => {
                if (value && value > 0 && value !== this.serviceDuration) {
                    this.serviceDuration = value;
                    this.generateAllSlots();
                }
            });
        },

        generateAllSlots() {
            if (this.providers.length === 0) {
                this.timeSlots = [];
                return;
            }

            let earliest = 24 * 60;
            let latest = 0;

            this.providers.forEach(provider => {
                const start = this.timeToMinutes(provider.workStart);
                const end = this.timeToMinutes(provider.workEnd);
                if (start < earliest) earliest = start;
                if (end > latest) latest = end;
            });

            const slots = [];
            let current = earliest;
            while (current < latest) {
                slots.push(this.minutesToTime(current));
                current += 10;
            }
            this.timeSlots = slots;
        },

        timeToMinutes(time) {
            const parts = time.split(':');
            return parseInt(parts[0]) * 60 + parseInt(parts[1]);
        },

        minutesToTime(minutes) {
            const h = Math.floor(minutes / 60).toString().padStart(2, '0');
            const m = (minutes % 60).toString().padStart(2, '0');
            return h + ':' + m;
        },

        /**
         * تحديد نوع الـ slot: available, booked, timeoff, outside
         */
        getSlotType(provider, slot) {
            const slotStart = this.timeToMinutes(slot);
            const slotEnd = slotStart + this.serviceDuration;
            const workStart = this.timeToMinutes(provider.workStart);
            const workEnd = this.timeToMinutes(provider.workEnd);

            // خارج ساعات العمل
            if (slotStart < workStart || slotEnd > workEnd) {
                return { type: 'outside' };
            }

            // تحقق من العطلات (TimeOff) - أولوية أعلى من المواعيد
            for (const off of provider.timeOffs) {
                const offStart = this.timeToMinutes(off.start);
                const offEnd = this.timeToMinutes(off.end);
                if (slotStart < offEnd && slotEnd > offStart) {
                    return { type: 'timeoff', data: off };
                }
            }

            // تحقق من المواعيد المحجوزة
            for (const apt of provider.appointments) {
                const aptStart = this.timeToMinutes(apt.start);
                const aptEnd = this.timeToMinutes(apt.end);
                if (slotStart < aptEnd && slotEnd > aptStart) {
                    return { type: 'booked', data: apt };
                }
            }

            return { type: 'available' };
        },

        /**
         * تحقق إذا كان الـ slot متاح للحجز
         */
        isSlotAvailable(provider, slot) {
            const slotType = this.getSlotType(provider, slot);
            return slotType.type === 'available';
        },

        isSlotInRange(providerId, slot) {
            if (this.selectedProvider !== providerId || !this.selectedTime) {
                return false;
            }
            const slotMin = this.timeToMinutes(slot);
            const slotEndMin = slotMin + 10;
            const startMin = this.timeToMinutes(this.selectedTime);
            const endMin = startMin + this.serviceDuration;

            return slotMin < endMin && slotEndMin > startMin;
        },

        getSlotCoverage(providerId, slot) {
            if (this.selectedProvider !== providerId || !this.selectedTime) {
                return { start: 0, end: 0 };
            }

            const slotMin = this.timeToMinutes(slot);
            const slotEndMin = slotMin + 10;
            const startMin = this.timeToMinutes(this.selectedTime);
            const endMin = startMin + this.serviceDuration;

            const overlapStart = Math.max(slotMin, startMin);
            const overlapEnd = Math.min(slotEndMin, endMin);

            if (overlapStart >= overlapEnd) {
                return { start: 0, end: 0 };
            }

            const startPercent = ((overlapStart - slotMin) / 10) * 100;
            const endPercent = ((overlapEnd - slotMin) / 10) * 100;

            return { start: startPercent, end: endPercent };
        },

        getSlotGradient(providerId, slot) {
            const coverage = this.getSlotCoverage(providerId, slot);
            if (coverage.start === 0 && coverage.end === 0) {
                return '';
            }

            return 'linear-gradient(to right, transparent 0%, transparent ' + coverage.start + '%, #10b981 ' + coverage.start + '%, #10b981 ' + coverage.end + '%, transparent ' + coverage.end + '%, transparent 100%)';
        },

        selectSlot(providerId, slot) {
            this.selectedTime = slot;
            this.selectedProvider = providerId;

            $wire.$set('data.provider_id', providerId);
            $wire.$set('data.start_time', slot);
        }
    }"
>
    @if(empty($providers))
        <div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-8">
            <div class="text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-200">
                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h3 class="mt-3 text-sm font-semibold text-gray-900">
                    لا يوجد مقدمو خدمة متاحين
                </h3>
                <p class="mt-1 text-xs text-gray-500">
                    اختر الخدمة والتاريخ أولاً
                </p>
            </div>
        </div>
    @else
        <div class="space-y-3">
            <!-- Header -->
            <div class="flex items-center justify-between rounded-lg bg-white border border-gray-200 p-3">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-100">
                        <svg class="h-3.5 w-3.5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">الجدول الزمني للمقدمين</h3>
                        <p class="text-xs text-gray-500">انقر على الوقت للحجز</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 text-xs">
                    <div class="flex items-center gap-1">
                        <div class="h-2.5 w-2.5 rounded border-2 border-primary-500 bg-white"></div>
                        <span class="text-gray-600">متاح</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="h-2.5 w-2.5 rounded bg-success-500"></div>
                        <span class="text-gray-600">محدد</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="h-2.5 w-2.5 rounded bg-red-500"></div>
                        <span class="text-gray-600">محجوز</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <div class="h-2.5 w-2.5 rounded bg-yellow-400"></div>
                        <span class="text-gray-600">عطلة</span>
                    </div>
                </div>
            </div>

            <!-- Duration Badge -->
            <div class="inline-flex items-center gap-2 rounded-lg bg-primary-50 px-3 py-1.5 text-xs">
                <svg class="h-3.5 w-3.5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
                <span class="font-medium text-primary-900">مدة الخدمة:</span>
                <span class="font-semibold text-primary-700" x-text="serviceDuration + ' دقيقة'"></span>
            </div>

            <!-- Timeline -->
            <div class="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
                <div class="divide-y divide-gray-200">
                    <template x-for="provider in providers" :key="provider.id">
                        <div class="flex hover:bg-gray-50 transition-colors">
                            <!-- Provider Name -->
                            <div class="w-32 flex-shrink-0 p-3 border-r border-gray-200 flex items-center gap-2">
                                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 text-white">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-900 truncate" x-text="provider.name"></p>
                                    <p class="text-[10px] text-gray-500" x-text="provider.workStart + ' - ' + provider.workEnd"></p>
                                </div>
                            </div>

                            <!-- Timeline Slots -->
                            <div class="flex-1 p-2 overflow-x-auto">
                                <div class="flex gap-0.5 pt-8 pb-2" style="min-width: max-content;">
                                    <template x-for="(slot, idx) in timeSlots" :key="idx">
                                        <div class="relative">
                                            <!-- Time Label - خارج templates ليظهر دائماً -->
                                            <span
                                                class="absolute -top-7 left-1/2 -translate-x-1/2 text-xs font-bold whitespace-nowrap px-1.5 py-0.5 rounded shadow-sm z-20 border"
                                                :class="isSlotInRange(provider.id, slot) ? 'text-white bg-success-600 border-success-700' : 'text-gray-800 bg-white border-gray-300'"
                                                x-text="slot"
                                            ></span>

                                            <!-- Available Slot -->
                                            <template x-if="getSlotType(provider, slot).type === 'available'">
                                                <button
                                                    type="button"
                                                    @click="selectSlot(provider.id, slot)"
                                                    class="h-20 w-10 rounded border-2 transition-all relative group overflow-hidden"
                                                    :class="{
                                                        'border-success-500 shadow-md': isSlotInRange(provider.id, slot),
                                                        'border-primary-300 bg-white hover:border-primary-500 hover:bg-primary-50': !isSlotInRange(provider.id, slot)
                                                    }"
                                                    :style="isSlotInRange(provider.id, slot) ? 'background: ' + getSlotGradient(provider.id, slot) : ''"
                                                >
                                                    <!-- Checkmark -->
                                                    <div
                                                        x-show="selectedTime === slot && selectedProvider === provider.id"
                                                        class="absolute inset-0 flex items-center justify-center bg-success-600 bg-opacity-90 rounded"
                                                    >
                                                        <svg class="h-4 w-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </div>
                                                </button>
                                            </template>

                                            <!-- Booked Slot (محجوز - أحمر) -->
                                            <template x-if="getSlotType(provider, slot).type === 'booked'">
                                                <div class="h-20 w-10 rounded bg-red-500 flex items-center justify-center relative group cursor-not-allowed">
                                                    <svg class="h-3 w-3 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <!-- Tooltip -->
                                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-30 w-48">
                                                        <div class="bg-gray-900 text-white text-xs rounded-lg py-2 px-3 shadow-lg">
                                                            <div class="font-semibold mb-1">موعد محجوز</div>
                                                            <div class="space-y-1 text-[10px]">
                                                                <div><span class="text-gray-300">العميل:</span> <span x-text="getSlotType(provider, slot).data.customer"></span></div>
                                                                <div><span class="text-gray-300">الوقت:</span> <span x-text="getSlotType(provider, slot).data.start + ' - ' + getSlotType(provider, slot).data.end"></span></div>
                                                            </div>
                                                            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
                                                                <div class="border-4 border-transparent border-t-gray-900"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- TimeOff Slot (عطلة - أصفر) -->
                                            <template x-if="getSlotType(provider, slot).type === 'timeoff'">
                                                <div class="h-20 w-10 rounded bg-yellow-400 flex items-center justify-center relative group cursor-not-allowed">
                                                    <svg class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    <!-- Tooltip -->
                                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover:block z-30 w-40">
                                                        <div class="bg-gray-900 text-white text-xs rounded-lg py-2 px-3 shadow-lg">
                                                            <div class="font-semibold mb-1">عطلة</div>
                                                            <div class="text-[10px]">
                                                                <span class="text-gray-300">من:</span> <span x-text="getSlotType(provider, slot).data.start"></span>
                                                                <br>
                                                                <span class="text-gray-300">إلى:</span> <span x-text="getSlotType(provider, slot).data.end"></span>
                                                            </div>
                                                            <div class="absolute top-full left-1/2 -translate-x-1/2 -mt-1">
                                                                <div class="border-4 border-transparent border-t-gray-900"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>

                                            <!-- Outside Working Hours -->
                                            <template x-if="getSlotType(provider, slot).type === 'outside'">
                                                <div class="h-20 w-10 rounded bg-gray-100 opacity-30"></div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    @endif
</div>
