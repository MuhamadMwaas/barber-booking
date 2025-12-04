<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditService extends EditRecord
{
    protected static string $resource = ServiceResource::class;

    private array $translations = [];
    private array $providers = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->successNotificationTitle(__('resources.service.deleted_notification')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('resources.service.updated_notification');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing translations keyed by language_id
        $translations = [];
        foreach ($this->record->translations as $translation) {
            $translations[$translation->language_id] = [
                'id' => $translation->id,
                'language_id' => $translation->language_id,
                'language_code' => $translation->language_code,
                'name' => $translation->name,
                'description' => $translation->description,
            ];
        }

        $data['translations'] = $translations;

        // Load existing providers
        $providers = [];
        foreach ($this->record->providers as $provider) {
            $providers[] = [
                'provider_id' => $provider->id,
                'custom_price' => $provider->pivot->custom_price,
                'custom_duration' => $provider->pivot->custom_duration,
                'is_active' => $provider->pivot->is_active,
                'notes' => $provider->pivot->notes,
            ];
        }

        $data['providers'] = $providers;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure sort_order has a value
        if (empty($data['sort_order'])) {
            $data['sort_order'] = \App\Models\Service::max('sort_order') + 1 ?? 1;
        }

        // Extract translations to handle separately
        $this->translations = $data['translations'] ?? [];
        unset($data['translations']);

        // Extract providers to handle separately
        $this->providers = $data['providers'] ?? [];
        unset($data['providers']);

        return $data;
    }

    protected function afterSave(): void
    {
        // Handle image uploads
        $this->handleImageUpload('image_url');
        $this->handleImageUpload('icon_url');

        // Handle translations
        $this->handleTranslations();

        // Handle providers
        $this->handleProviders();

        // Show provider notification
        $providersCount = $this->record->providers()->count();
        Notification::make()
            ->success()
            ->title(__('resources.service.providers_updated'))
            ->body(__('resources.service.providers_count_message', ['count' => $providersCount]))
            ->send();

        // Show translations notification
        if (!empty($this->translations)) {
            Notification::make()
                ->success()
                ->title(__('resources.service.translations_updated'))
                ->body(__('resources.service.translations_updated_message', ['count' => count($this->translations)]))
                ->send();
        }
    }

    protected function handleImageUpload($filed): void
    {
        try {
            $formState = $this->form->getRawState();
            $serviceImage = $formState[$filed] ?? null;

            if($filed=='image_url'){
                $relation='image';
            }else{
                $relation='icon';
            }

            if (!$serviceImage) {
                return;
            }

            if (is_array($serviceImage)) {
                $serviceImage = array_shift($serviceImage);
            }

            if (!$serviceImage || !is_string($serviceImage)) {
                return;
            }

            // Check if this is a new upload (temp path) or existing file
            if (strpos($serviceImage, 'temp/') === false) {
                return; // Not a new upload, skip
            }

            logger()->info("Processing service image: {$serviceImage}");

            $tempPath = storage_path( 'app/public/' .$serviceImage);

            if (!file_exists($tempPath)) {
                logger()->warning("service image file not found at: {$tempPath}");
                return;
            }

            $mimeType = mime_content_type($tempPath);
            $originalName = basename($serviceImage);

            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $originalName,
                $mimeType,
                null,
                true
            );

            $this->record->updateProfileImage($uploadedFile,$relation);

            @unlink($tempPath);

            logger()->info("service image uploaded successfully for service {$this->record->id}");

        } catch (\Exception $e) {
            logger()->error('Failed to upload service image: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function handleTranslations(): void
    {
        if (empty($this->translations)) {
            return;
        }

        try {
            // $this->translations is now keyed by language_id
            $languageIds = array_keys($this->translations);

            // Delete translations for languages that are no longer in the form
            $this->record->translations()
                ->whereNotIn('language_id', $languageIds)
                ->delete();

            // Update or create translations
            foreach ($this->translations as $languageId => $translation) {
                if (empty($translation['name'])) {
                    continue;
                }

                $this->record->translations()->updateOrCreate(
                    [
                        'service_id' => $this->record->id,
                        'language_id' => $translation['language_id'] ?? $languageId,
                    ],
                    [
                        'language_code' => $translation['language_code'] ?? null,
                        'name' => $translation['name'],
                        'description' => $translation['description'] ?? null,
                    ]
                );
            }

            logger()->info("Translations updated successfully for service {$this->record->id}");

        } catch (\Exception $e) {
            logger()->error('Failed to update translations: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function handleProviders(): void
    {
        try {
            // Detach all existing providers
            $this->record->providers()->detach();

            // Attach new providers
            if (!empty($this->providers)) {
                foreach ($this->providers as $provider) {
                    if (empty($provider['provider_id'])) {
                        continue;
                    }

                    $this->record->providers()->attach($provider['provider_id'], [
                        'custom_price' => $provider['custom_price'] ?? null,
                        'custom_duration' => $provider['custom_duration'] ?? null,
                        'is_active' => $provider['is_active'] ?? true,
                        'notes' => $provider['notes'] ?? null,
                    ]);
                }
            }

            logger()->info("Providers updated successfully for service {$this->record->id}");

        } catch (\Exception $e) {
            logger()->error('Failed to update providers: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
