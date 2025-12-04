<?php

namespace App\Filament\Resources\ServiceCategories\Pages;

use App\Filament\Resources\ServiceCategories\ServiceCategoryResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceCategory extends CreateRecord
{
    protected static string $resource = ServiceCategoryResource::class;

    private array $translations = [];

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('resources.service_category.created_notification');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure sort_order has a value
        if (empty($data['sort_order'])) {
            $data['sort_order'] = \App\Models\ServiceCategory::max('sort_order') + 1 ?? 1;
        }

        // Extract translations to handle separately
        $this->translations = $data['translations'] ?? [];
        unset($data['translations']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Handle image upload
        $this->handleImageUpload();

        // Handle translations
        $this->handleTranslations();

        // Show translations notification
        if (!empty($this->translations)) {
            Notification::make()
                ->success()
                ->title(__('resources.service_category.translations_saved'))
                ->body(__('resources.service_category.translations_saved_message', ['count' => count($this->translations)]))
                ->send();
        }
    }

    protected function handleImageUpload(): void
    {
        try {
            $formState = $this->form->getRawState();
            $categoryImage = $formState['image_url'] ?? null;

            if (!$categoryImage) {
                logger()->info('No category image file in form state');
                return;
            }

            if (is_array($categoryImage)) {
                $categoryImage = array_shift($categoryImage);
            }

            if (!$categoryImage || !is_string($categoryImage)) {
                logger()->info('Category image file is not a valid string');
                return;
            }

            logger()->info('Category name: ' . $this->record->name);
            logger()->info("Processing category image: {$categoryImage}");

            $tempPath = storage_path('app/public' . $categoryImage);

            if (!file_exists($tempPath)) {
                logger()->warning("Category image file not found at: {$tempPath}");
                return;
            }

            $mimeType = mime_content_type($tempPath);
            $originalName = basename($categoryImage);

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

            $this->record->updateImage($uploadedFile);

            @unlink($tempPath);

            logger()->info("Category image uploaded successfully for category {$this->record->id}");

        } catch (\Exception $e) {
            logger()->error('Failed to upload category image: ' . $e->getMessage(), [
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
            // $this->translations is keyed by language_id
            foreach ($this->translations as $languageId => $translation) {
                if (empty($translation['name'])) {
                    continue;
                }

                $this->record->translations()->create([
                    'service_category_id' => $this->record->id,
                    'language_id' => $translation['language_id'] ?? $languageId,
                    'language_code' => $translation['language_code'] ?? null,
                    'name' => $translation['name'],
                    'description' => $translation['description'] ?? null,
                ]);
            }

            logger()->info("Translations saved successfully for service category {$this->record->id}");

        } catch (\Exception $e) {
            logger()->error('Failed to save translations: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
