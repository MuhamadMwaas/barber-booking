<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Personal Information Section
                Section::make(__('resources.user.personal_information'))
                    ->schema([
                        FileUpload::make('profile_image_file')
                            ->label(__('resources.user.profile_image'))
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            // ->circleCropper()
                            ->disk('public')
                            ->directory('temp/uploads')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                            ->helperText(__('resources.user.profile_image_helper'))
                            ->afterStateHydrated(function (FileUpload $component, $state, $record) {
                                // Only set the existing image when initially loading the form
                                if ($record && $record->profile_image && $record->profile_image->path && !$state) {
                                    $component->state([$record->profile_image->path]);
                                }
                            })
                            ->saveRelationshipsUsing(null)
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('first_name')
                                    ->label(__('resources.user.first_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->autocomplete('given-name'),

                                TextInput::make('last_name')
                                    ->label(__('resources.user.last_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->autocomplete('family-name'),
                            ]),
                    ])
                    ->collapsible()
                    ->columns(1),

                // Contact Information Section
                Section::make(__('resources.user.contact_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label(__('resources.user.email'))
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->autocomplete('email'),

                                TextInput::make('phone')
                                    ->label(__('resources.user.phone'))
                                    ->tel()
                                    ->maxLength(20)
                                    ->autocomplete('tel'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('address')
                                    ->label(__('resources.user.address'))
                                    ->maxLength(255)
                                    ->autocomplete('street-address'),

                                TextInput::make('city')
                                    ->label(__('resources.user.city'))
                                    ->maxLength(60)
                                    ->autocomplete('address-level2'),
                            ]),
                    ])
                    ->collapsible()
                    ->columns(1),

                // Account Settings Section
                Section::make(__('resources.user.account_settings'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('role')
                                    ->label(__('resources.user.role'))
                                    ->placeholder(__('resources.user.select_role'))
                                    ->options([
                                        'admin' => __('resources.user.admin'),
                                        'customer' => __('resources.user.customer'),
                                        'manager' => __('resources.user.manager'),
                                        'provider' => __('resources.user.provider'),
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->helperText(__('resources.user.role_helper'))
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        // Clear branch if role is customer or admin
                                        if (in_array($state, ['customer', 'admin'])) {
                                            $set('branch_id', null);
                                        }
                                    }),

                                Select::make('branch_id')
                                    ->label(__('resources.user.branch'))
                                    ->placeholder(__('resources.user.select_branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->helperText(__('resources.user.branch_helper'))
                                    ->visible(fn ($get) => in_array($get('role'), ['manager', 'provider'])),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('locale')
                                    ->label(__('resources.user.locale'))
                                    ->placeholder(__('resources.user.select_language'))
                                    ->options([
                                        'ar' => __('resources.user.arabic'),
                                        'en' => __('resources.user.english'),
                                        'de' => __('resources.user.german'),
                                    ])
                                    ->required()
                                    ->default('en')
                                    ->native(false)
                                    ->helperText(__('resources.user.locale_helper')),

                                Toggle::make('is_active')
                                    ->label(__('resources.user.is_active'))
                                    ->default(true)
                                    ->inline(false)
                                    ->helperText(__('resources.user.status_helper')),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('password')
                                    ->label(__('resources.user.password'))
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $context): bool => $context === 'create')
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->rule(Password::default())
                                    ->autocomplete('new-password')
                                    ->helperText(__('resources.user.password_helper'))
                                    ->maxLength(255)
                                    ->confirmed(),

                                TextInput::make('password_confirmation')
                                    ->label(__('resources.user.password_confirmation'))
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $context): bool => $context === 'create')
                                    ->dehydrated(false)
                                    ->maxLength(255),
                            ]),
                    ])
                    ->collapsible()
                    ->columns(1),

                // Additional Information Section
                Section::make(__('resources.user.additional_information'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('resources.user.notes'))
                            ->rows(3)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->columns(1),
            ]);
    }
}
