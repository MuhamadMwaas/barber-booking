<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Filament\Forms\Components\PasswordConfirmationInput;
use App\Filament\Forms\Components\PasswordInput;
use App\Support\ImageUploadRules;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Role;

class UserForm
{
    public static function configure(Schema $schema, string $mode = 'all'): Schema
    {
        $isCustomerMode = $mode === 'customer';
        $excludedRoles = ['SuperAdmin', 'admin'];

        if ($mode === 'management') {
            $excludedRoles[] = 'customer';
        }

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
                            // AUTH-06: the same ceilings the API enforces. The
                            // dimensions rule is the one that matters — maxSize
                            // bounds the bytes uploaded, not the pixels they
                            // decode into.
                            ->maxSize(ImageUploadRules::maxKilobytes())
                            ->acceptedFileTypes(ImageUploadRules::mimeTypes())
                            ->rules([ImageUploadRules::dimensions()])
                            ->helperText(__('resources.user.profile_image_helper'))
                            // The existing image is hydrated by the Edit page's
                            // mutateFormDataBeforeFill(). Do not override
                            // afterStateHydrated() here: that replaces Filament's own
                            // hook, which keys the state by upload UUID and drops paths
                            // that no longer exist on disk. Without it a stale path
                            // survives alongside a new upload and gets picked instead.
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
                                    ->unique(ignoreRecord: true)
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
                                    ->options(fn () => Role::where('guard_name', 'web')
                                        ->when($isCustomerMode, fn ($query) => $query->where('name', 'customer'))
                                        ->when(! $isCustomerMode, fn ($query) => $query->whereNotIn('name', $excludedRoles))
                                        ->pluck('name', 'name')
                                        ->toArray())
                                    ->required()
                                    ->default($isCustomerMode ? 'customer' : null)
                                    ->visible(! $isCustomerMode)
                                    ->native(false)
                                    ->searchable()
                                    ->preload()
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
                                PasswordInput::make('password')
                                    ->label(__('resources.user.password'))
                                    ->required(fn (string $context): bool => $context === 'create')
                                    ->helperText(fn (string $context): ?string => $context === 'edit'
                                        ? __('passwords.requirements.edit_helper')
                                        : null),

                                PasswordConfirmationInput::make('password_confirmation')
                                    ->label(__('resources.user.password_confirmation'))
                                    ->required(fn (string $context): bool => $context === 'create'),
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
