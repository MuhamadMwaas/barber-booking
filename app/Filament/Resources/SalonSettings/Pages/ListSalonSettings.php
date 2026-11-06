<?php

namespace App\Filament\Resources\SalonSettings\Pages;

use App\Filament\Resources\SalonSettings\SalonSettingCatalog;
use App\Filament\Resources\SalonSettings\SalonSettingResource;
use App\Models\SalonSetting;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListSalonSettings extends ListRecords
{
    protected static string $resource = SalonSettingResource::class;

    /** One tab per group that has settings, catalog order first. */
    public function getTabs(): array
    {
        $counts = SalonSetting::query()
            ->selectRaw('setting_group, count(*) as total')
            ->whereNotNull('setting_group')
            ->groupBy('setting_group')
            ->pluck('total', 'setting_group');

        $groups = collect(SalonSettingCatalog::groups())
            ->intersect($counts->keys())
            ->merge($counts->keys())
            ->unique();

        $tabs = ['all' => Tab::make(__('resources.salon_setting.all_settings'))->badge($counts->sum())];

        foreach ($groups as $group) {
            $tabs[$group] = Tab::make(SalonSettingCatalog::groupLabel($group))
                ->icon(SalonSettingCatalog::groupIcon($group))
                ->badge($counts[$group])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('setting_group', $group));
        }

        return $tabs;
    }
}
