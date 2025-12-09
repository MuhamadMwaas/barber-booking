<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field">
    <div class="timeline-field-container">
        @php
            // الحصول على userId من عدة مصادر محتملة
            $formState = $getState();
            $record = $getRecord();

            $userId = $formState['user_id']
                ?? $formState['selected_user_id']
                ?? data_get($record, 'user_id')
                ?? request()->query('userId');
        @endphp

        @if($userId)
            {{-- عرض Timeline Component --}}
            @livewire('weekly-schedule-timeline', [
                'userId' => $userId,
                'readOnly' => true,
                'showBranchSchedule' => true,
            ], key('timeline-' . $userId))
        @else
            {{-- رسالة لاختيار موظف --}}
            <div class="flex flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 p-12 text-center dark:border-gray-700 dark:bg-gray-900">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-900/30">
                    <x-heroicon-o-user-group class="h-8 w-8 text-primary-600 dark:text-primary-400" />
                </div>
                <h3 class="mt-4 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ __('resources.provider_scheduled_work.no_provider_selected') }}
                </h3>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('resources.provider_scheduled_work.no_provider_selected_desc') }}
                </p>
                <div class="mt-4 flex items-center gap-2 text-xs text-gray-400 dark:text-gray-600">
                    <x-heroicon-o-arrow-left class="h-4 w-4" />
                    <span>{{ __('resources.provider_scheduled_work.go_to_provider_tab') }}</span>
                </div>
            </div>
        @endif
    </div>
</x-dynamic-component>
