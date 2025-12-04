<?php

namespace App\Filament\Resources\ServiceCategories\Pages;

use App\Filament\Resources\ServiceCategories\ServiceCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditServiceCategory extends EditRecord
{
    protected static string $resource = ServiceCategoryResource::class;

    private array $translations = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->successNotificationTitle(__('resources.service_category.deleted_notification')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return __('resources.service_category.updated_notification');
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

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

    protected function afterSave(): void
    {
        // Handle image upload
        $this->handleImageUpload();

        // Handle translations
        $this->handleTranslations();

        // Show translations notification
        if (!empty($this->translations)) {
            Notification::make()
                ->success()
                ->title(__('resources.service_category.translations_updated'))
                ->body(__('resources.service_category.translations_updated_message', ['count' => count($this->translations)]))
                ->send();
        }
    }

    protected function handleImageUpload(): void
    {
        try {
            $formState = $this->form->getRawState();
            $categoryImage = $formState['image_url'] ?? null;

            if (!$categoryImage) {
                return;
            }

            if (is_array($categoryImage)) {
                $categoryImage = array_shift($categoryImage);
            }

            if (!$categoryImage || !is_string($categoryImage)) {
                return;
            }

            // Check if this is a new upload (temp path) or existing file
            if (strpos($categoryImage, 'temp/') === false) {
                return; // Not a new upload, skip
            }

            logger()->info("Processing category image: {$categoryImage}");

            $tempPath = storage_path('app/public/' . $categoryImage);

            if (!file_exists($tempPath)) {
                logger()->warning("Category image file not found at: {$tempPath}");
                return;
            }

            $mimeType = mime_content_type($tempPath);
            $originalName = basename($categoryImage);

            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $originalName,
                $mimeType,
                null,
                true
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
                        'service_category_id' => $this->record->id,
                        'language_id' => $translation['language_id'] ?? $languageId,
                    ],
                    [
                        'language_code' => $translation['language_code'] ?? null,
                        'name' => $translation['name'],
                        'description' => $translation['description'] ?? null,
                    ]
                );
            }

            logger()->info("Translations updated successfully for service category {$this->record->id}");

        } catch (\Exception $e) {
            logger()->error('Failed to update translations: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
