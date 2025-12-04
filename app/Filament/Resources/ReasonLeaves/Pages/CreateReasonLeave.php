<?php

namespace App\Filament\Resources\ReasonLeaves\Pages;

use App\Filament\Resources\ReasonLeaves\ReasonLeaveResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateReasonLeave extends CreateRecord
{
    protected static string $resource = ReasonLeaveResource::class;

    private array $translations = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Extract translations from form data
        $this->translations = $data['translations'] ?? [];
        unset($data['translations']);

        return $data;
    }

    protected function afterCreate(): void
    {
        // Save translations
        $this->handleTranslations();

        // Show success notification
        if (!empty($this->translations)) {
            Notification::make()
                ->success()
                ->title(__('resources.reason_leave.created_notification'))
                ->body(__('resources.reason_leave.translations_saved'))
                ->send();
        }
    }

    protected function handleTranslations(): void
    {
        foreach ($this->translations as $languageId => $translation) {
            // Skip if both name and description are empty
            if (empty($translation['name']) && empty($translation['description'])) {
                continue;
            }

            // The $languageId is already the key from "translations.{$language->id}"
            // We can use it directly or get it from the hidden field
            $actualLanguageId = $translation['language_id'] ?? $languageId;
            $languageRecord = \App\Models\Language::find($actualLanguageId);
            $this->record->translations()->create([
                'reason_leave_id' => $this->record->id,
                'language_id' => $actualLanguageId,
                'language_code' => $languageRecord ? $languageRecord->code : null,
                'name' => $translation['name'] ?? null,
                'description' => $translation['description'] ?? null,
            ]);
        }
    }
}
