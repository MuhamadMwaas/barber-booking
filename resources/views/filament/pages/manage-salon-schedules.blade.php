<x-filament-panels::page>
    {{--
        صفحة إدارة جداول مواعيد الصالون

        هذا القالب يدمج مكون Livewire داخل صفحة Filament
        مع الاستفادة من تنسيقات Filament وميزاته
    --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])

    <div>
    {{-- Header Info Card --}}
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('salon_schedule.page_title') }}
            </x-slot>

            <x-slot name="description">
                {{ __('salon_schedule.page_subheading') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-blue-100">
                        <x-heroicon-o-clock class="w-5 h-5 text-blue-600" />
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900">
                            {{ __('salon_schedule.manage_opening_hours') }}
                        </h4>
                        <p class="text-gray-500">
                            {{ __('salon_schedule.weekly_schedule_description') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-green-100">
                        <x-heroicon-o-calendar class="w-5 h-5 text-green-600" />
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900">
                            {{ __('salon_schedule.weekly_schedule') }}
                        </h4>
                        <p class="text-gray-500">
                            Set different hours for each day
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-purple-100">
                        <x-heroicon-o-chart-bar class="w-5 h-5 text-purple-600" />
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900">
                            Visual Timeline
                        </h4>
                        <p class="text-gray-500">
                            See working hours on timeline
                        </p>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Main Schedule Manager Component --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5">
        <div class="fi-section-content p-6">
            @livewire('salon-schedule-manager', ['branchId' => request()->query('branchId')])
        </div>
    </div>

    {{-- Help Section --}}

    </div>
</x-filament-panels::page>
