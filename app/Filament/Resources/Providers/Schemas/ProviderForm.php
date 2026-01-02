<?php

namespace App\Filament\Resources\Providers\Schemas;

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

class ProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Personal Information Section
                Section::make(__('resources.provider_resource.personal_information'))
                    ->schema([
                        FileUpload::make('profile_image_file')
                            ->label(__('resources.provider_resource.profile_image'))
                            ->image()
                            ->avatar()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('temp/uploads')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                            ->helperText(__('resources.provider_resource.profile_image_helper'))
                            ->afterStateHydrated(function (FileUpload $component, $state, $record) {
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
                                    ->label(__('resources.provider_resource.first_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->autocomplete('given-name'),

                                TextInput::make('last_name')
                                    ->label(__('resources.provider_resource.last_name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->autocomplete('family-name'),
                            ]),
                    ])
                    ->collapsible()
                    ->columns(1),

                // Contact Information Section
                Section::make(__('resources.provider_resource.contact_information'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('email')
                                    ->label(__('resources.provider_resource.email'))
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->autocomplete('email'),

                                TextInput::make('phone')
                                    ->label(__('resources.provider_resource.phone'))
                                    ->tel()
                                    ->required()
                                    ->maxLength(20)
                                    ->autocomplete('tel'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('address')
                                    ->label(__('resources.provider_resource.address'))
                                    ->maxLength(255)
                                    ->autocomplete('street-address'),

                                TextInput::make('city')
                                    ->label(__('resources.provider_resource.city'))
                                    ->maxLength(60)
                                    ->autocomplete('address-level2'),
                            ]),
                    ])
                    ->collapsible()
                    ->columns(1),

                // Account Settings Section
                Section::make(__('resources.provider_resource.account_settings'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('branch_id')
                                    ->label(__('resources.provider_resource.branch'))
                                    ->placeholder(__('resources.provider_resource.select_branch'))
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false)
                                    ->helperText(__('resources.provider_resource.branch_helper')),

                                Select::make('locale')
                                    ->label(__('resources.provider_resource.locale'))
                                    ->placeholder(__('resources.provider_resource.select_language'))
                                    ->options([
                                        'ar' => __('resources.provider_resource.arabic'),
                                        'en' => __('resources.provider_resource.english'),
                                        'de' => __('resources.provider_resource.german'),
                                    ])
                                    ->required()
                                    ->default('en')
                                    ->native(false)
                                    ->helperText(__('resources.provider_resource.locale_helper')),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_active')
                                    ->label(__('resources.provider_resource.is_active'))
                                    ->default(true)
                                    ->inline(false)
                                    ->helperText(__('resources.provider_resource.status_helper')),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('password')
                                    ->label(__('resources.provider_resource.password'))
                                    ->password()
                                    ->revealable()
                                    ->required(fn (string $context): bool => $context === 'create')
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->rule(Password::default())
                                    ->autocomplete('new-password')
                                    ->helperText(__('resources.provider_resource.password_helper'))
                                    ->maxLength(255)
                                    ->confirmed(),

                                TextInput::make('password_confirmation')
                                    ->label(__('resources.provider_resource.password_confirmation'))
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
                Section::make(__('resources.provider_resource.additional_information'))
                    ->schema([
                        Textarea::make('notes')
                            ->label(__('resources.provider_resource.notes'))
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
