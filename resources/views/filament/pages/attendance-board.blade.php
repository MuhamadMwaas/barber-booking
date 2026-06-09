<x-filament-panels::page>
    <style>
        /* Diagonal stripes for an open (not-yet-checked-out) session bar. */
        .attendance-stripes {
            background-image: repeating-linear-gradient(
                45deg,
                rgba(255, 255, 255, 0.45) 0,
                rgba(255, 255, 255, 0.45) 4px,
                transparent 4px,
                transparent 8px
            );
            background-size: 16px 16px;
            animation: attendance-stripes-move 1s linear infinite;
        }
        @keyframes attendance-stripes-move {
            from { background-position: 0 0; }
            to   { background-position: 16px 0; }
        }
        /* Stable, predictable card heights so the grid reads as a tidy board. */
        .att-card { background: #ffffff; }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @php $cards = $this->cards(); @endphp

    <div class="space-y-6">
        {{-- Toolbar --}}
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-gray-500">
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-5 rounded-full bg-emerald-500"></span>
                    {{ __('attendance_board.actual') }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-5 rounded-full bg-amber-500 attendance-stripes"></span>
                    {{ __('attendance_board.open_short') }}
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-2.5 w-5 rounded bg-indigo-100 ring-1 ring-inset ring-indigo-300/70"></span>
                    {{ __('attendance_board.scheduled') }}
                </span>
            </div>

            <div class="relative w-full lg:w-72">
                <input
                    type="search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="{{ __('attendance_board.search_placeholder') }}"
                    class="w-full rounded-xl border-gray-300 bg-white px-3 py-2 pe-9 text-sm text-gray-900 shadow-sm outline-none transition placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500"
                />
                <div class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-gray-400">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-4 w-4" />
                </div>
            </div>
        </div>

        {{-- Cards grid --}}
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 2xl:grid-cols-3">
            @forelse ($cards as $card)
                @php
                    $statusStyles = match ($card['status']) {
                        'open'   => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                        'closed' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
                        default  => 'bg-gray-100 text-gray-500 ring-gray-500/20',
                    };
                    $statusLabel = match ($card['status']) {
                        'open'   => __('attendance_board.status_open'),
                        'closed' => __('attendance_board.status_closed'),
                        default  => $card['is_work_day'] ? __('attendance_board.status_none_workday') : __('attendance_board.status_off'),
                    };
                @endphp

                <button
                    type="button"
                    wire:key="att-card-{{ $card['id'] }}"
                    wire:click="openHistory({{ $card['id'] }})"
                    class="att-card group relative flex flex-col overflow-hidden rounded-2xl border border-gray-200 text-start shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                >
                    {{-- decorative header glow --}}
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-primary-50/70 to-transparent"></div>

                    <div class="relative flex flex-1 flex-col p-5">
                        {{-- Provider header --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-base font-bold text-white shadow-sm">
                                    {{ $card['initial'] }}
                                    @if ($card['open'])
                                        <span class="absolute -end-1 -top-1 flex h-3 w-3">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                            <span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                                        </span>
                                    @endif
                                </div>

                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-bold text-gray-900">
                                        {{ $card['name'] }}
                                    </h3>
                                    <div class="mt-0.5 flex items-center gap-1 text-xs text-gray-500">
                                        <x-filament::icon icon="heroicon-m-building-storefront" class="h-3.5 w-3.5 shrink-0" />
                                        <span class="truncate">{{ $card['branch'] ?? __('attendance_board.no_branch') }}</span>
                                    </div>
                                </div>
                            </div>

                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset {{ $statusStyles }}">
                                @if ($card['open'])
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                @endif
                                {{ $statusLabel }}
                            </span>
                        </div>

                        @if ($card['latest_day'])
                            {{-- Hero: most-recent attendance day --}}
                            <div class="mt-5 rounded-2xl border border-gray-100 bg-gray-50/70 p-4">
                                @include('filament.pages.partials.attendance-timeline-line', ['day' => $card['latest_day'], 'variant' => 'hero'])

                                <div class="mt-3 flex items-center justify-between border-t border-gray-200/70 pt-3 text-xs text-gray-500">
                                    <span class="inline-flex items-center gap-1">
                                        <x-filament::icon icon="heroicon-m-arrow-right-on-rectangle" class="h-4 w-4 text-emerald-500" />
                                        <span class="font-medium text-gray-700">{{ $card['latest_day']['first_in'] ?? '—' }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <x-filament::icon icon="heroicon-m-arrow-left-on-rectangle" class="h-4 w-4 text-sky-500" />
                                        <span class="font-medium text-gray-700">{{ $card['latest_day']['last_out'] ?? __('attendance_board.open_short') }}</span>
                                    </span>
                                </div>
                            </div>

                            {{-- Last 3 days --}}
                            @if (count($card['recent_days']) > 1)
                                <div class="mt-4">
                                    <h4 class="mb-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                        {{ __('attendance_board.last_3_days') }}
                                    </h4>
                                    <div class="space-y-3">
                                        @foreach ($card['recent_days'] as $day)
                                            @include('filament.pages.partials.attendance-timeline-line', ['day' => $day, 'variant' => 'mini'])
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @else
                            {{-- Compact empty state --}}
                            <div class="mt-5 flex items-center gap-2 rounded-xl border border-dashed border-gray-200 bg-gray-50/50 px-4 py-3 text-xs text-gray-400">
                                <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4 shrink-0" />
                                {{ __('attendance_board.no_recent') }}
                            </div>
                        @endif

                        {{-- Footer hint --}}
                        <div class="mt-auto flex items-center justify-center gap-1 pt-4 text-xs font-medium text-primary-600 opacity-0 transition group-hover:opacity-100">
                            {{ __('attendance_board.view_full_history') }}
                            <x-filament::icon icon="heroicon-m-arrow-long-right" class="h-4 w-4 rtl:hidden" />
                            <x-filament::icon icon="heroicon-m-arrow-long-left" class="hidden h-4 w-4 rtl:inline" />
                        </div>
                    </div>
                </button>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
                    <x-filament::icon icon="heroicon-o-users" class="mx-auto h-10 w-10 text-gray-400" />
                    <h3 class="mt-3 text-base font-bold text-gray-900">{{ __('attendance_board.empty_title') }}</h3>
                    <p class="mt-1 text-sm text-gray-500">{{ __('attendance_board.empty_body') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ───────────────── History modal (infinite scroll) ───────────────── --}}
    <x-filament::modal id="attendance-history" width="4xl" :close-button="true">
        <x-slot name="heading">
            {{ __('attendance_board.history_title', ['name' => $selectedProviderName ?? '']) }}
        </x-slot>
        <x-slot name="description">
            {{ __('attendance_board.history_subtitle') }}
        </x-slot>

        <div
            x-data="{
                loading: false,
                io: null,
                load() {
                    if (this.loading || ! $wire.historyHasMore) return;
                    this.loading = true;
                    $wire.loadMoreHistory().finally(() => {
                        this.loading = false;
                        this.$nextTick(() => this.bind());
                    });
                },
                bind() {
                    if (! this.$refs.sentinel) return;
                    this.io && this.io.disconnect();
                    this.io = new IntersectionObserver((entries) => {
                        if (entries[0].isIntersecting) this.load();
                    }, { root: this.$refs.scroll, threshold: 0.1 });
                    this.io.observe(this.$refs.sentinel);
                },
            }"
            x-init="$nextTick(() => bind())"
        >
            <div x-ref="scroll" class="max-h-[70vh] space-y-3 overflow-y-auto pe-1">
                @forelse ($historyDays as $day)
                    <div
                        wire:key="hist-{{ $selectedProviderId }}-{{ $day['date'] }}"
                        class="rounded-xl border border-gray-100 bg-white p-4 shadow-sm"
                    >
                        @include('filament.pages.partials.attendance-timeline-line', ['day' => $day, 'variant' => 'modal'])

                        @if ($day['has_records'])
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($day['bars'] as $bar)
                                    <span class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs {{ $bar['is_open']
                                        ? 'border-amber-200 bg-amber-50 text-amber-700'
                                        : 'border-gray-200 bg-gray-50 text-gray-700' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $bar['is_open'] ? 'bg-amber-500' : 'bg-emerald-500' }}"></span>
                                        <span class="font-medium tabular-nums">{{ $bar['in'] }} – {{ $bar['out'] ?? __('attendance_board.no_checkout') }}</span>
                                        @if ($bar['duration'])
                                            <span class="text-gray-400">· {{ $bar['duration'] }}</span>
                                        @endif
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-gray-200 p-10 text-center">
                        <x-filament::icon icon="heroicon-o-calendar-days" class="mx-auto h-8 w-8 text-gray-400" />
                        <p class="mt-2 text-sm text-gray-500">{{ __('attendance_board.no_records') }}</p>
                    </div>
                @endforelse

                {{-- Sentinel / manual fallback --}}
                @if ($historyHasMore)
                    <div x-ref="sentinel" class="pt-1">
                        <button
                            type="button"
                            @click="load()"
                            class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 py-2.5 text-sm text-gray-500 transition hover:border-primary-400 hover:text-primary-600"
                        >
                            <span wire:loading.remove wire:target="loadMoreHistory">{{ __('attendance_board.load_more') }}</span>
                            <span wire:loading wire:target="loadMoreHistory" class="inline-flex items-center gap-2">
                                <x-filament::loading-indicator class="h-4 w-4" />
                                {{ __('attendance_board.loading') }}
                            </span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </x-filament::modal>
</x-filament-panels::page>
