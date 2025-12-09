<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field">

    @php
        // Get userId from various sources
        $formState = $getState();
        $record = $getRecord();
        $userId = $formState['user_id']
            ?? $formState['selected_user_id']
            ?? data_get($record, 'user_id')
            ?? request()->query('userId');
    @endphp

    @if($userId)
        {{-- Professional Schedule Manager with Timeline --}}
        <div class="provider-schedule-manager space-y-6">
            {{-- Timeline View Component --}}
            <div class="timeline-section">
                @livewire('weekly-schedule-timeline', [
                    'userId' => $userId,
                    'readOnly' => true,
                    'showBranchSchedule' => true,
                ], key('timeline-' . $userId))
            </div>

            {{-- Shift Management Section --}}
            <div class="shift-management-section">
                @livewire('shift-manager', [
                    'userId' => $userId,
                    'readOnly' => false,
                ], key('shift-manager-' . $userId))
            </div>
        </div>
    @else
        {{-- Error State: No Provider Selected --}}
        <div class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gradient-to-br from-gray-50 to-gray-100 p-16 text-center dark:border-gray-700 dark:from-gray-900 dark:to-gray-800">
            <div class="relative">
                <div class="absolute inset-0 -m-4 animate-ping rounded-full bg-danger-500/20"></div>
                <div class="relative mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-danger-100 to-danger-200 dark:from-danger-900/30 dark:to-danger-800/30">
                    <x-heroicon-o-exclamation-triangle class="h-10 w-10 text-danger-600 dark:text-danger-400" />
                </div>
            </div>

            <h3 class="mt-6 text-xl font-bold text-gray-900 dark:text-white">
                {{ __('resources.provider_scheduled_work.no_provider_selected') }}
            </h3>

            <p class="mt-2 max-w-sm text-sm text-gray-600 dark:text-gray-400">
                {{ __('resources.provider_scheduled_work.no_provider_selected_desc') }}
            </p>

            <div class="mt-6 flex items-center gap-2 rounded-lg bg-white px-4 py-2 shadow-sm dark:bg-gray-800">
                <x-heroicon-m-information-circle class="h-5 w-5 text-primary-500" />
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ __('resources.provider_scheduled_work.select_provider_first') }}
                </span>
            </div>
        </div>
    @endif

</x-dynamic-component>

<style>
    .provider-schedule-manager {
        animation: fadeInUp 0.4s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Smooth transitions */
    .timeline-section,
    .shift-management-section {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .timeline-section:hover,
    .shift-management-section:hover {
        transform: translateY(-2px);
    }
</style>
