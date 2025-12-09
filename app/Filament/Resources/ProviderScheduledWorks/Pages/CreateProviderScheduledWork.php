<?php

namespace App\Filament\Resources\ProviderScheduledWorks\Pages;

use App\Filament\Pages\ManageProviderSchedules;
use App\Filament\Resources\ProviderScheduledWorks\ProviderScheduledWorkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProviderScheduledWork extends CreateRecord
{
    protected static string $resource = ProviderScheduledWorkResource::class;

    /**
     * Redirect to ManageProviderSchedules page instead of traditional create form
     * since schedule management is now handled through the timeline-based interface
     */
    public function mount(): void
    {
        // Get userId from query parameter if available
        $userId = request()->query('userId');

        // Redirect to the manage schedules page
    }
}
