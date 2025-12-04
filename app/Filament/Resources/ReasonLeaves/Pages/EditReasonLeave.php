<?php

namespace App\Filament\Resources\ReasonLeaves\Pages;

use App\Filament\Resources\ReasonLeaves\ReasonLeaveResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditReasonLeave extends EditRecord
{
    protected static string $resource = ReasonLeaveResource::class;

    private array $translations = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load existing translations
        $translations = [];
        foreach ($this->record->translations as $translation) {
            $translations[$translation->language_id] = [
                'id' => $translation->id,
                'language_id' => $translation->language_id,
                'name' => $translation->name,
                'description' => $translation->description,
            ];
        }
        $data['translations'] = $translations;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract translations from form data
        $this->translations = $data['translations'] ?? [];
        unset($data['translations']);

        return $data;
    }

    protected function afterSave(): void
    {
        // Update translations
        $this->handleTranslations();

        // Show success notification
        if (!empty($this->translations)) {
            Notification::make()
                ->success()
                ->title(__('resources.reason_leave.updated_notification'))
                ->body(__('resources.reason_leave.translations_saved'))
                ->send();
        }
    }

    protected function handleTranslations(): void
    {
        // Get language IDs from submitted translations (keys are the language IDs)
        $submittedLanguageIds = array_keys($this->translations);

        // Delete translations that are no longer present
        $this->record->translations()
            ->whereNotIn('language_id', $submittedLanguageIds)
            ->delete();

        // Update or create translations
        foreach ($this->translations as $languageId => $translation) {
            // The $languageId is the key from "translations.{$language->id}"
            // We can also get it from the hidden field if available
            $actualLanguageId = $translation['language_id'] ?? $languageId;

            // Skip if both name and description are empty
            if (empty($translation['name']) && empty($translation['description'])) {
                // Delete if exists
                $this->record->translations()
                    ->where('language_id', $actualLanguageId)
                    ->delete();
                continue;
            }

            $this->record->translations()->updateOrCreate(
                [
                    'reason_leave_id' => $this->record->id,
                    'language_id' => $actualLanguageId
                ],
                [
                    'name' => $translation['name'] ?? null,
                    'description' => $translation['description'] ?? null,
                ]
            );
        }
    }
}
