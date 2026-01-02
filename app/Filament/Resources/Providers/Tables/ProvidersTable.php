<?php

namespace App\Filament\Resources\Providers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class ProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('profile_image_url')
                    ->label(__('resources.provider_resource.avatar'))
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->full_name) . '&color=7F9CF5&background=EBF4FF'),

                TextColumn::make('full_name')
                    ->label(__('resources.provider_resource.full_name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record) => $record->email),

                TextColumn::make('phone')
                    ->label(__('resources.provider_resource.phone'))
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage(__('resources.provider_resource.phone_copied'))
                    ->placeholder('—'),

                TextColumn::make('branch.name')
                    ->label(__('resources.provider_resource.branch'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('resources.provider_resource.no_branch')),

                TextColumn::make('services_count')
                    ->label(__('resources.provider_resource.services'))
                    ->counts('services')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('appointments_as_provider_count')
                    ->label(__('resources.provider_resource.appointments'))
                    ->counts('appointmentsAsProvider')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('upcoming_time_offs')
                    ->label(__('resources.provider_resource.upcoming_leaves'))
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(function ($record) {
                        return $record->timeOffs()
                            ->where('start_date', '>=', now()->toDateString())
                            ->count();
                    }),

                IconColumn::make('is_active')
                    ->label(__('resources.provider_resource.status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                TextColumn::make('locale')
                    ->label(__('resources.provider_resource.language'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ar' => 'success',
                        'en' => 'info',
                        'de' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label(__('resources.provider_resource.joined_at'))
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('resources.provider_resource.status'))
                    ->placeholder(__('resources.provider_resource.all_providers'))
                    ->trueLabel(__('resources.provider_resource.active_only'))
                    ->falseLabel(__('resources.provider_resource.inactive_only')),

                SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->preload()
                    ->multiple()
                    ->label(__('resources.provider_resource.filter_by_branch')),

                SelectFilter::make('locale')
                    ->options([
                        'ar' => __('resources.provider_resource.arabic'),
                        'en' => __('resources.provider_resource.english'),
                        'de' => __('resources.provider_resource.german'),
                    ])
                    ->label(__('resources.provider_resource.filter_by_language')),

                TernaryFilter::make('has_upcoming_leaves')
                    ->label(__('resources.provider_resource.has_upcoming_leaves'))
                    ->placeholder(__('resources.provider_resource.all_providers'))
                    ->trueLabel(__('resources.provider_resource.with_upcoming_leaves'))
                    ->falseLabel(__('resources.provider_resource.without_upcoming_leaves'))
                    ->queries(
                        true: fn ($query) => $query->whereHas('timeOffs', function ($q) {
                            $q->where('start_date', '>=', now()->toDateString());
                        }),
                        false: fn ($query) => $query->whereDoesntHave('timeOffs', function ($q) {
                            $q->where('start_date', '>=', now()->toDateString());
                        }),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('add_time_off')
                    ->label(__('resources.provider_resource.add_leave'))
                    ->icon('heroicon-o-calendar-days')
                    ->color('warning')
                    ->url(fn ($record) => route('filament.admin.resources.providers.view', ['record' => $record->id]) . '#time-offs')
                    ->tooltip(__('resources.provider_resource.add_leave_tooltip')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('resources.provider_resource.no_providers_yet'))
            ->emptyStateDescription(__('resources.provider_resource.create_first_provider'))
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
