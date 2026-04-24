@php
    use Carbon\Carbon;
    use App\Filament\Resources\Appointments\Schemas\AppointmentForm;

    $date            = $date ?? Carbon::now()->format('Y-m-d');
    $services        = $services ?? [];
    $selectedProvider = $selectedProvider ?? null;
    $selectedTime    = $selectedTime ?? null;
    $serviceDuration = $serviceDuration ?? collect($services)->sum('duration_minutes') ?: 30;

    // Build stable wire:key — re-creates Alpine component when date or services change
    $serviceIds = collect($services)->pluck('service_id')->filter()->sort()->values()->toArray();
    $wireKey    = 'apt-timeline-' . ($date ?? 'nodate') . '-' . md5(implode(',', $serviceIds));

    // Fetch providers from PHP (server-side, always fresh per Livewire render)
    $providers = AppointmentForm::getProvidersTimeline($date, $services);
@endphp
@vite(['resources/css/app.css', 'resources/js/app.js'])

{{--
    wire:key forces Livewire to destroy + recreate this DOM node when date or
    services change, ensuring Alpine.js re-initialises with fresh $providers data.
--}}
<div
    wire:key="{{ $wireKey }}"
    class="w-full"
    x-data="{
        providers:       {{ json_encode($providers) }},
        serviceDuration: {{ (int) $serviceDuration }},
        selectedTime:    @js($selectedTime ?? ''),
        selectedProvider: {{ $selectedProvider ? (int)$selectedProvider : 'null' }},
        timeSlots: [],

        init() {
            this.generateAllSlots();

            // Keep in sync with manual inputs in the rest of the form
            this.$watch(() => this.$wire.get('data.start_time'), (v) => {
                if (v !== undefined && v !== this.selectedTime) this.selectedTime = v ?? '';
            });
            this.$watch(() => this.$wire.get('data.provider_id'), (v) => {
                if (v !== undefined && v !== this.selectedProvider) this.selectedProvider = v ?? null;
            });
            this.$watch(() => this.$wire.get('data.duration_minutes'), (v) => {
                if (v && parseInt(v) > 0 && parseInt(v) !== this.serviceDuration) {
                    this.serviceDuration = parseInt(v);
                    this.generateAllSlots();
                }
            });
        },

        /* Build a shared time axis from the earliest work-start to the latest
           work-end across all providers. Slots are 10-minute increments. */
        generateAllSlots() {
            if (!this.providers.length) { this.timeSlots = []; return; }

            let earliest = 1440, latest = 0;
            this.providers.forEach(p => {
                const s = this.toMin(p.workStart), e = this.toMin(p.workEnd);
                if (s < earliest) earliest = s;
                if (e > latest)   latest   = e;
            });

            const slots = [];
            for (let m = earliest; m < latest; m += 10) slots.push(this.toHHMM(m));
            this.timeSlots = slots;
        },

        toMin(t) {
            const [h, m] = t.split(':').map(Number);
            return h * 60 + m;
        },
        toHHMM(min) {
            return String(Math.floor(min / 60)).padStart(2,'0') + ':' + String(min % 60).padStart(2,'0');
        },

        /* Returns { type: 'available' | 'booked' | 'timeoff' | 'outside', data? } */
        slotType(provider, slot) {
            const sS = this.toMin(slot), sE = sS + this.serviceDuration;
            const wS = this.toMin(provider.workStart), wE = this.toMin(provider.workEnd);

            if (sS < wS || sE > wE) return { type: 'outside' };

            for (const off of provider.timeOffs) {
                if (sS < this.toMin(off.end) && sE > this.toMin(off.start))
                    return { type: 'timeoff', data: off };
            }
            for (const apt of provider.appointments) {
                if (sS < this.toMin(apt.end) && sE > this.toMin(apt.start))
                    return { type: 'booked', data: apt };
            }
            return { type: 'available' };
        },

        /* True if this slot belongs to the currently selected range */
        inRange(providerId, slot) {
            if (this.selectedProvider !== providerId || !this.selectedTime) return false;
            const sMin  = this.toMin(slot),
                  selS  = this.toMin(this.selectedTime),
                  selE  = selS + this.serviceDuration;
            return sMin < selE && (sMin + 10) > selS;
        },

        /* Gradient for partial coverage of a 10-min cell */
        gradient(providerId, slot) {
            if (!this.inRange(providerId, slot)) return '';
            const sMin = this.toMin(slot),
                  selS = this.toMin(this.selectedTime),
                  selE = selS + this.serviceDuration,
                  oS   = Math.max(sMin, selS),
                  oE   = Math.min(sMin + 10, selE);
            if (oS >= oE) return '';
            const pS = ((oS - sMin) / 10) * 100,
                  pE = ((oE - sMin) / 10) * 100;
            return `linear-gradient(to right,transparent ${pS}%,#10b981 ${pS}%,#10b981 ${pE}%,transparent ${pE}%)`;
        },

        selectSlot(providerId, slot) {
            this.selectedTime     = slot;
            this.selectedProvider = providerId;
            $wire.$set('data.provider_id', providerId);
            $wire.$set('data.start_time',  slot);
        }
    }"
>
    {{-- ───── Empty state ───── --}}
    @if(empty($providers))
        <div class="rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 dark:bg-gray-800 dark:border-gray-600 p-8">
            <div class="text-center">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700">
                    <svg class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <h3 class="mt-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                    {{ __('resources.appointment.no_providers_available') }}
                </h3>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ __('resources.appointment.select_service_date_first') }}
                </p>
            </div>
        </div>

    @else
        <div class="space-y-3">

            {{-- ── Legend header ── --}}
            <div class="flex items-center justify-between rounded-lg bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-3">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-900">
                        <svg class="h-3.5 w-3.5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('resources.appointment.providers_timeline') }}
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ __('resources.appointment.click_slot_to_book') }}
                        </p>
                    </div>
                </div>

                {{-- Legend --}}
                <div class="flex items-center gap-3 text-xs flex-wrap">
                    <div class="flex items-center gap-1.5">
                        <div class="h-3 w-3 rounded border-2 border-primary-400 bg-white dark:bg-gray-900"></div>
                        <span class="text-gray-600 dark:text-gray-400">{{ __('resources.appointment.slot_available') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-3 w-3 rounded bg-emerald-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">{{ __('resources.appointment.slot_selected') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-3 w-3 rounded bg-red-500"></div>
                        <span class="text-gray-600 dark:text-gray-400">{{ __('resources.appointment.slot_booked') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-3 w-3 rounded bg-amber-400"></div>
                        <span class="text-gray-600 dark:text-gray-400">{{ __('resources.appointment.slot_timeoff') }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="h-3 w-3 rounded bg-gray-200 dark:bg-gray-600"></div>
                        <span class="text-gray-600 dark:text-gray-400">{{ __('resources.appointment.slot_outside') }}</span>
                    </div>
                </div>
            </div>

            {{-- ── Duration badge ── --}}
            <div class="inline-flex items-center gap-2 rounded-lg bg-primary-50 dark:bg-primary-900/30 px-3 py-1.5 text-xs">
                <svg class="h-3.5 w-3.5 text-primary-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
                <span class="font-medium text-primary-900 dark:text-primary-100">
                    {{ __('resources.appointment.service_duration') }}:
                </span>
                <span class="font-semibold text-primary-700 dark:text-primary-300"
                      x-text="serviceDuration + ' {{ __('resources.appointment.duration_suffix') }}'">
                </span>
            </div>

            {{-- ── Timeline grid ── --}}
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    <template x-for="provider in providers" :key="provider.id">
                        <div class="flex hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">

                            {{-- Provider name column (fixed width, RTL-safe) --}}
                            <div class="w-36 flex-shrink-0 p-3 border-e border-gray-200 dark:border-gray-700 flex items-start gap-2">
                                <div class="mt-0.5 flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-primary-500 to-primary-600 text-white">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold text-gray-900 dark:text-gray-100 break-words leading-tight"
                                       x-text="provider.name"></p>
                                    <p class="mt-0.5 text-[10px] text-gray-500 dark:text-gray-400"
                                       x-text="provider.workStart + ' – ' + provider.workEnd"></p>
                                </div>
                            </div>

                            {{--
                                Slot row.
                                dir="ltr" forces left-to-right layout for the time axis regardless of
                                the page's RTL direction — time always flows left → right.
                            --}}
                            <div class="flex-1 overflow-x-auto" dir="ltr">
                                <div class="flex gap-0.5 pt-8 pb-2 px-1" style="min-width: max-content;">
                                    <template x-for="(slot, idx) in timeSlots" :key="idx">
                                        <div class="relative flex-shrink-0">

                                            {{-- Time label (always shown) --}}
                                            <span
                                                class="pointer-events-none absolute -top-7 left-1/2 -translate-x-1/2 whitespace-nowrap rounded border px-1 py-0.5 text-[10px] font-semibold z-10 shadow-sm"
                                                :class="inRange(provider.id, slot)
                                                    ? 'bg-emerald-600 border-emerald-700 text-white'
                                                    : 'bg-white dark:bg-gray-700 border-gray-300 dark:border-gray-500 text-gray-700 dark:text-gray-300'"
                                                x-text="slot"
                                            ></span>

                                            {{-- AVAILABLE --}}
                                            <template x-if="slotType(provider, slot).type === 'available'">
                                                <button
                                                    type="button"
                                                    @click="selectSlot(provider.id, slot)"
                                                    class="relative h-20 w-10 rounded border-2 transition-all duration-150 overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                                    :class="inRange(provider.id, slot)
                                                        ? 'border-emerald-500 shadow-md'
                                                        : 'border-primary-200 bg-white dark:bg-gray-700 dark:border-primary-600 hover:border-primary-500 hover:bg-primary-50 dark:hover:bg-primary-900/40'"
                                                    :style="inRange(provider.id, slot) ? 'background:' + gradient(provider.id, slot) : ''"
                                                    :title="slot"
                                                >
                                                    {{-- Checkmark overlay when selected --}}
                                                    <div
                                                        x-show="selectedTime === slot && selectedProvider === provider.id"
                                                        class="absolute inset-0 flex items-center justify-center rounded bg-emerald-600/90"
                                                    >
                                                        <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                  clip-rule="evenodd"/>
                                                        </svg>
                                                    </div>
                                                </button>
                                            </template>

                                            {{-- BOOKED (red) --}}
                                            <template x-if="slotType(provider, slot).type === 'booked'">
                                                <div class="group relative h-20 w-10 cursor-not-allowed rounded bg-red-500 flex items-center justify-center">
                                                    <svg class="h-3.5 w-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                                        <path fill-rule="evenodd"
                                                              d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"
                                                              clip-rule="evenodd"/>
                                                    </svg>
                                                    {{-- Tooltip (dir=ltr so text is always readable) --}}
                                                    <div class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 hidden w-48 -translate-x-1/2 group-hover:block" dir="ltr">
                                                        <div class="rounded-lg bg-gray-900 px-3 py-2 text-xs text-white shadow-lg">
                                                            <p class="mb-1 font-semibold">{{ __('resources.appointment.booked_slot') }}</p>
                                                            <p class="text-[10px] text-gray-300" x-text="slotType(provider, slot).data?.customer"></p>
                                                            <p class="text-[10px] text-gray-400"
                                                               x-text="slotType(provider, slot).data?.start + ' – ' + slotType(provider, slot).data?.end"></p>
                                                        </div>
                                                        <div class="mx-auto w-0 border-4 border-transparent border-t-gray-900"></div>
                                                    </div>
                                                </div>
                                            </template>

                                            {{-- TIME-OFF (amber) --}}
                                            <template x-if="slotType(provider, slot).type === 'timeoff'">
                                                <div class="group relative h-20 w-10 cursor-not-allowed rounded bg-amber-400 flex items-center justify-center">
                                                    <svg class="h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    {{-- Tooltip --}}
                                                    <div class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 hidden w-40 -translate-x-1/2 group-hover:block" dir="ltr">
                                                        <div class="rounded-lg bg-gray-900 px-3 py-2 text-xs text-white shadow-lg">
                                                            <p class="mb-1 font-semibold">{{ __('resources.appointment.timeoff_slot') }}</p>
                                                            <p class="text-[10px] text-gray-300"
                                                               x-text="slotType(provider, slot).data?.start + ' – ' + slotType(provider, slot).data?.end"></p>
                                                        </div>
                                                        <div class="mx-auto w-0 border-4 border-transparent border-t-gray-900"></div>
                                                    </div>
                                                </div>
                                            </template>

                                            {{-- OUTSIDE working hours — subtle dashed cell --}}
                                            <template x-if="slotType(provider, slot).type === 'outside'">
                                                <div class="h-20 w-10 rounded border border-dashed border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/40 opacity-50"></div>
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
