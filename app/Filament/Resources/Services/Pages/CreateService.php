<?php

namespace App\Filament\Resources\Services\Pages;

use App\Filament\Resources\Services\ServiceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateService extends CreateRecord
{
    protected static string $resource = ServiceResource::class;

    private array $translations = [];
    private array $providers = [];

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('resources.service.created_notification');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
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

    protected function afterCreate(): void
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
        if ($providersCount > 0) {
            Notification::make()
                ->success()
                ->title(__('resources.service.providers_assigned'))
                ->body(__('resources.service.providers_assigned_message', ['count' => $providersCount]))
                ->send();
        }

        // Show translations notification
        if (!empty($this->translations)) {
            Notification::make()
                ->success()
                ->title(__('resources.service.translations_saved'))
                ->body(__('resources.service.translations_saved_message', ['count' => count($this->translations)]))
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
                logger()->info('No profile image file in form state');
                return;
            }

            if (is_array($serviceImage)) {
                $serviceImage = array_shift($serviceImage);
            }

            if (!$serviceImage || !is_string($serviceImage)) {
                logger()->info('service image file is not a valid string');
                return;
            }
                logger()->info('service name '.$this->record->name);

            logger()->info("Processing service image: {$serviceImage}");

            $tempPath = storage_path('temp/services/uploads/' . $serviceImage);

            if (!file_exists($tempPath)) {
                logger()->warning("service image file not found at: {$tempPath}");
                return;
            }

            $mimeType = mime_content_type($tempPath);
            $originalName = basename($serviceImage);

            logger()->info("Creating UploadedFile instance", [
                'path' => $tempPath,
                'name' => $originalName,
                'mime' => $mimeType
            ]);

            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $originalName,
                $mimeType,
                null,
                true // test mode - don't validate file
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
            foreach ($this->translations as $languageId => $translation) {
                if (empty($translation['name'])) {
                    continue;
                }

                $this->record->translations()->create([
                    'service_id' => $this->record->id,
                    'language_id' => $translation['language_id'] ?? $languageId,
                    'language_code' => $translation['language_code'] ?? null,
                    'name' => $translation['name'],
                    'description' => $translation['description'] ?? null,
                ]);
            }

            logger()->info("Translations saved successfully for service {$this->record->id}");

        } catch (\Exception $e) {
            logger()->error('Failed to save translations: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function handleProviders(): void
    {
        if (empty($this->providers)) {
            return;
        }

        try {
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

            logger()->info("Providers assigned successfully for service {$this->record->id}");

        } catch (\Exception $e) {
            logger()->error('Failed to assign providers: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
