<x-filament-panels::page>
    {{--
        صفحة إدارة جداول الموظفين

        هذا القالب يدمج مكون Livewire داخل صفحة Filament
        مع الاستفادة من تنسيقات Filament وميزاته

        ⚠️ ملاحظة: يجب التأكد من تسجيل المكون في AppServiceProvider
        أو استخدام الـ Full Class Path
    --}}
@vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Header Info Card --}}
    <div class="mb-6">
        <x-filament::section>
            <x-slot name="heading">
                {{ __('schedule.info_title') }}
            </x-slot>

            <x-slot name="description">
                {{ __('schedule.info_description') }}
            </x-slot>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                        <x-heroicon-o-clock class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white">
                            {{ __('schedule.feature_shifts_title') }}
                        </h4>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ __('schedule.feature_shifts_desc') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900/30">
                        <x-heroicon-o-document-duplicate class="w-5 h-5 text-green-600 dark:text-green-400" />
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white">
                            {{ __('schedule.feature_copy_title') }}
                        </h4>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ __('schedule.feature_copy_desc') }}
                        </p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-purple-100 dark:bg-purple-900/30">
                        <x-heroicon-o-shield-check class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white">
                            {{ __('schedule.feature_validation_title') }}
                        </h4>
                        <p class="text-gray-500 dark:text-gray-400">
                            {{ __('schedule.feature_validation_desc') }}
                        </p>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>

    {{-- Main Schedule Manager Component --}}
    {{--
        طريقة 1: استخدام الاسم المختصر (يتطلب تسجيل في AppServiceProvider)
        @livewire('schedule-manager')

        طريقة 2: استخدام Full Class Path (يعمل مباشرة بدون تسجيل)
    --}}
    <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 ">
        <div class="fi-section-content p-6">
            {{-- تمرير userId إلى Livewire component --}}
            @livewire('schedule-manager', ['userId' => $userId])
        </div>
    </div>

    {{-- Help Section --}}
    <div class="mt-6">
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-question-mark-circle class="w-5 h-5" />
                    {{ __('schedule.help_title') }}
                </div>
            </x-slot>

            <div class="prose dark:prose-invert max-w-none text-sm">
                <h4>{{ __('schedule.help_how_to_use') }}</h4>
                <ol>
                    <li>{{ __('schedule.help_step_1') }}</li>
                    <li>{{ __('schedule.help_step_2') }}</li>
                    <li>{{ __('schedule.help_step_3') }}</li>
                    <li>{{ __('schedule.help_step_4') }}</li>
                    <li>{{ __('schedule.help_step_5') }}</li>
                </ol>

                <h4>{{ __('schedule.help_tips_title') }}</h4>
                <ul>
                    <li>{{ __('schedule.help_tip_1') }}</li>
                    <li>{{ __('schedule.help_tip_2') }}</li>
                    <li>{{ __('schedule.help_tip_3') }}</li>
                    <li>{{ __('schedule.help_tip_4') }}</li>
                </ul>

                <h4>{{ __('schedule.help_shortcuts_title') }}</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div class="flex items-center gap-2">
                        <kbd class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 rounded">{{ __('schedule.copy_day') }}</kbd>
                        <span>{{ __('schedule.help_shortcut_copy_day') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <kbd class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 rounded">{{ __('schedule.apply_to_all_days') }}</kbd>
                        <span>{{ __('schedule.help_shortcut_apply_all') }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <kbd class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-700 rounded">{{ __('schedule.bulk_paste') }}</kbd>
                        <span>{{ __('schedule.help_shortcut_bulk') }}</span>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
