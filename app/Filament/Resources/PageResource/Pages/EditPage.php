<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\PageResource\Schemas\PageForm;
use App\Models\PageTranslation;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected array $translationsData = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('resources.page_resource.preview'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn () => PageResource::getUrl('view', ['record' => $this->record]))
                ->openUrlInNewTab(),
        ];
    }

    protected function getFormSchema(): array
    {
        return PageForm::make();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load translations and convert to form format
        $page = $this->record;

        foreach ($page->translations as $translation) {
            $data['translations'][$translation->lang] = [
                'title' => $translation->title,
                'content' => $translation->content,
                'meta' => $translation->meta ?? ['title' => null, 'description' => null],
            ];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Extract translations from form data
        $this->translationsData = $data['translations'] ?? [];
        unset($data['translations']);

        return $data;
    }

    protected function afterSave(): void
    {
        $defaultLocale = config('app.fallback_locale', 'ar');

        foreach ($this->translationsData as $lang => $translation) {
            // Skip empty translations (except default language which is validated)
            if (empty($translation['title']) && empty($translation['content']) && $lang !== $defaultLocale) {
                // Delete translation if it exists but now empty
                PageTranslation::where('page_id', $this->record->id)
                    ->where('lang', $lang)
                    ->delete();
                continue;
            }

            // Prepare meta data
            $meta = [
                'title' => $translation['meta']['title'] ?? null,
                'description' => $translation['meta']['description'] ?? null,
            ];

            // Update or create translation
            PageTranslation::updateOrCreate(
                [
                    'page_id' => $this->record->id,
                    'lang' => $lang,
                ],
                [
                    'title' => $translation['title'] ?? '',
                    'content' => $translation['content'] ?? '',
                    'meta' => $meta,
                ]
            );
        }

        Notification::make()
            ->title(__('resources.page_resource.saved_successfully'))
            ->success()
            ->send();
    }

    protected function getFormValidationRules(): array
    {
        $defaultLocale = config('app.fallback_locale', 'ar');

        return [
            "translations.{$defaultLocale}.title" => 'required|string|max:255',
            "translations.{$defaultLocale}.content" => 'required|string',
            // SEO fields are optional
            "translations.{$defaultLocale}.meta.title" => 'nullable|string|max:60',
            "translations.{$defaultLocale}.meta.description" => 'nullable|string|max:160',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
