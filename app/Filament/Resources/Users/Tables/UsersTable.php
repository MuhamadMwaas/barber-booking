<?php

namespace App\Filament\Resources\Users\Tables;


use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class UsersTable
{
       public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // صورة المستخدم
                ImageColumn::make('profile_image_url')
                    ->label(__('resources.avatar'))
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->full_name) . '&color=7F9CF5&background=EBF4FF'),

                // الاسم الكامل
                TextColumn::make('full_name')
                    ->label(__('resources.full_name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record) => $record->email),

                // الدور (Role من Spatie)
                TextColumn::make('roles.name')
                    ->label(__('resources.role'))
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'admin' => 'danger',
                        'manager' => 'warning',
                        'provider' => 'success',
                        'customer' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                // رقم الهاتف
                TextColumn::make('phone')
                    ->label(__('resources.phone'))
                    ->icon('heroicon-o-phone')
                    ->searchable()
                    ->copyable()
                    ->copyMessage(__('resources.phone_copied'))
                    ->placeholder('—'),

                // الفرع
                TextColumn::make('branch.name')
                    ->label(__('resources.branch'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->placeholder(__('resources.no_branch'))
                    ->toggleable(),

                // المدينة
                TextColumn::make('city')
                    ->label(__('resources.city'))
                    ->icon('heroicon-o-map-pin')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),

                // الحالة النشطة
                IconColumn::make('is_active')
                    ->label(__('resources.status'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                // اللغة المفضلة
                TextColumn::make('locale')
                    ->label(__('resources.user.language'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ar' => 'success',
                        'en' => 'info',
                        'de' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state))
                    ->toggleable(),

                // // التحقق من البريد
                // IconColumn::make('email_verified_at')
                //     ->label(__('resources.email_verified'))
                //     ->boolean()
                //     ->trueIcon('heroicon-o-check-badge')
                //     ->falseIcon('heroicon-o-exclamation-triangle')
                //     ->trueColor('success')
                //     ->falseColor('warning')
                //     ->sortable()
                //     ->toggleable(),

                // عدد الحجوزات (للعملاء)
                TextColumn::make('customer_appointments_count')
                    ->label(__('resources.appointments'))
                    ->counts('customerAppointments')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),


                // عدد الحجوزات المكتملة (للمقدمين)
                TextColumn::make('appointments_finshed_as_provider_count')
                    ->label(__('resources.completed_services'))
                    ->counts('appointmentsFinshedAsProvider')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->toggleable(),


                // تاريخ الإنشاء
                TextColumn::make('created_at')
                    ->label(__('resources.joined_at'))
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // آخر تحديث
                TextColumn::make('updated_at')
                    ->label(__('resources.last_updated'))
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // فلتر حسب الدور
                SelectFilter::make('role')
                    ->relationship('roles', 'name')
                    ->preload()
                    ->multiple()
                    ->label(__('resources.filter_by_role')),

                // فلتر حسب الحالة النشطة
                TernaryFilter::make('is_active')
                    ->label(__('resources.status'))
                    ->placeholder(__('resources.all_users'))
                    ->trueLabel(__('resources.active_only'))
                    ->falseLabel(__('resources.inactive_only')),

                // فلتر حسب الفرع
                SelectFilter::make('branch_id')
                    ->relationship('branch', 'name')
                    ->preload()
                    ->multiple()
                    ->label(__('resources.filter_by_branch')),

                // فلتر حسب اللغة
                SelectFilter::make('locale')
                    ->options([
                        'ar' => __('resources.arabic'),
                        'en' => __('resources.english'),
                        'de' => __('resources.german'),
                    ])
                    ->label(__('resources.filter_by_language')),


                    TernaryFilter::make('email_verified_at')
                    ->label(__('resources.email_verification'))
                    ->placeholder(__('resources.all_users'))
                    ->trueLabel(__('resources.verified_only'))
                    ->falseLabel(__('resources.unverified_only'))
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('email_verified_at'),
                        false: fn ($query) => $query->whereNull('email_verified_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('resources.no_users_yet'))
            ->emptyStateDescription(__('resources.create_first_user'))
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
