<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div class="prose prose-sm max-w-none dark:prose-invert">
        {{-- Introduction --}}
        <div class="mb-6 rounded-lg bg-primary-50 p-4 dark:bg-primary-950/20">
            <div class="flex items-start gap-3">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/50">
                    <x-heroicon-o-light-bulb class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                </div>
                <div>
                    <h4 class="mb-1 text-sm font-semibold text-primary-900 dark:text-primary-100">
                        {{ __('resources.provider_scheduled_work.instructions_intro_title') }}
                    </h4>
                    <p class="text-xs text-primary-700 dark:text-primary-300">
                        {{ __('resources.provider_scheduled_work.instructions_intro_text') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- How to Use --}}
        <div class="mb-6">
            <h4 class="mb-3 flex items-center gap-2 text-base font-bold text-gray-900 dark:text-white">
                <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                {{ __('resources.provider_scheduled_work.how_to_use') }}
            </h4>
            <ol class="space-y-2 text-sm text-gray-700 dark:text-gray-300">
                <li class="flex items-start gap-2">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">1</span>
                    <span>{{ __('resources.provider_scheduled_work.step_1') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">2</span>
                    <span>{{ __('resources.provider_scheduled_work.step_2') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">3</span>
                    <span>{{ __('resources.provider_scheduled_work.step_3') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">4</span>
                    <span>{{ __('resources.provider_scheduled_work.step_4') }}</span>
                </li>
            </ol>
        </div>

        {{-- Timeline Legend --}}
        <div class="mb-6">
            <h4 class="mb-3 flex items-center gap-2 text-base font-bold text-gray-900 dark:text-white">
                <x-heroicon-o-swatch class="h-5 w-5 text-gray-600 dark:text-gray-400" />
                {{ __('resources.provider_scheduled_work.timeline_legend') }}
            </h4>
            <div class="space-y-3">
                <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                    <div class="h-4 w-12 rounded bg-gradient-to-r from-primary-500 to-primary-600"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('resources.provider_scheduled_work.shift_block') }}</span>
                </div>
                <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                    <div class="h-4 w-12 rounded bg-blue-100/30 dark:bg-blue-900/20"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('resources.provider_scheduled_work.branch_hours_bg') }}</span>
                </div>
                <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-3 dark:bg-gray-800/50">
                    <div class="h-4 w-12 rounded bg-gray-300 dark:bg-gray-700"></div>
                    <span class="text-sm text-gray-700 dark:text-gray-300">{{ __('resources.provider_scheduled_work.day_off_indicator') }}</span>
                </div>
            </div>
        </div>

        {{-- Tips --}}
        <div class="rounded-lg border border-success-200 bg-success-50 p-4 dark:border-success-900/50 dark:bg-success-950/20">
            <h4 class="mb-2 flex items-center gap-2 text-sm font-semibold text-success-900 dark:text-success-100">
                <x-heroicon-o-sparkles class="h-5 w-5 text-success-600 dark:text-success-400" />
                {{ __('resources.provider_scheduled_work.tips_title') }}
            </h4>
            <ul class="space-y-1 text-xs text-success-800 dark:text-success-200">
                <li class="flex items-start gap-2">
                    <x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-success-600 dark:text-success-400" />
                    <span>{{ __('resources.provider_scheduled_work.tip_1') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-success-600 dark:text-success-400" />
                    <span>{{ __('resources.provider_scheduled_work.tip_2') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-success-600 dark:text-success-400" />
                    <span>{{ __('resources.provider_scheduled_work.tip_3') }}</span>
                </li>
            </ul>
        </div>

        {{-- Note --}}
        <div class="mt-4 rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-900/50 dark:bg-warning-950/20">
            <div class="flex items-start gap-2">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0 text-warning-600 dark:text-warning-400" />
                <p class="text-xs text-warning-800 dark:text-warning-200">
                    {{ __('resources.provider_scheduled_work.important_note') }}
                </p>
            </div>
        </div>
    </div>
</x-dynamic-component>
