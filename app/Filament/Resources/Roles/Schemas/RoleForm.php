<?php

namespace App\Filament\Resources\Roles\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('resources.role.role_details'))
                    ->description(__('resources.role.role_details_desc'))
                    ->icon('heroicon-o-shield-check')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('resources.role.name'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaNum()
                            ->columnSpanFull(),

                        Hidden::make('guard_name')
                            ->default('web'),
                    ]),

                Section::make(__('resources.role.permissions_section'))
                    ->description(__('resources.role.permissions_section_desc'))
                    ->icon('heroicon-o-key')
                    ->schema(
                        static::buildPermissionTabs()
                    ),
            ]);
    }

    private static function buildPermissionTabs(): array
    {
        $permissions = Permission::where('guard_name', 'web')
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        // Group permissions by prefix (before ":")
        $grouped = [];
        foreach ($permissions as $permission) {
            $parts = explode(':', $permission, 2);
            $group = $parts[0] ?? $permission;
            $grouped[$group][] = $permission;
        }

        ksort($grouped);

        if (empty($grouped)) {
            return [];
        }

        $tabs = [];
        foreach ($grouped as $group => $perms) {
            $options = [];
            foreach ($perms as $perm) {
                $parts = explode(':', $perm, 2);
                $ability = $parts[1] ?? $perm;
                $options[$perm] = ucfirst(str_replace('_', ' ', $ability));
            }

            $tabs[] = Tabs\Tab::make($group)
                ->icon(static::getGroupIcon($group))
                ->badge(count($perms))
                ->schema([
                    CheckboxList::make('permissions')
                        ->label('')
                        ->options($options)
                        ->columns(3)
                        ->bulkToggleable()
                        ->gridDirection('row'),
                ]);
        }

        return [
            Tabs::make('permissions_tabs')
                ->columnSpanFull()
                ->tabs($tabs),
        ];
    }

    private static function getGroupIcon(string $group): string
    {
        return match ($group) {
            'Appointment' => 'heroicon-o-calendar',
            'User' => 'heroicon-o-users',
            'Provider' => 'heroicon-o-user-group',
            'Service' => 'heroicon-o-scissors',
            'ServiceCategory' => 'heroicon-o-rectangle-stack',
            'Role' => 'heroicon-o-shield-check',
            'InvoiceTemplate' => 'heroicon-o-document-text',
            'PrintLog' => 'heroicon-o-printer',
            'PrinterSetting' => 'heroicon-o-cog-6-tooth',
            'Reports', 'ProviderReport' => 'heroicon-o-chart-bar',
            default => 'heroicon-o-key',
        };
    }
}
