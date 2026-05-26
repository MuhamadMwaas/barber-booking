<?php

namespace App\Filament\Resources\CmsPages\Pages;

use App\Filament\Resources\CmsPages\CmsPageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCmsPage extends EditRecord
{
    protected static string $resource = CmsPageResource::class;

    /* ──────────────────────────────────────────
     │  Header actions
     ────────────────────────────────────────── */

    protected function getHeaderActions(): array
    {
        return [
            // Visual mobile preview — opens styled phone mockup in a new tab
            Action::make('preview_design')
                ->label(__('cms.resource.action_preview'))
                ->icon('heroicon-o-device-phone-mobile')
                ->color('warning')
                ->url(fn () => route('admin.cms-preview', ['page' => $this->record->id]))
                ->openUrlInNewTab(),

            // Raw API response — for debugging/testing
            Action::make('preview_api')
                ->label(__('cms.resource.action_preview_api'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => url('/api/pages/' . $this->record->slug . '?lang=' . config('cms.default_language', 'ar')))
                ->openUrlInNewTab(),

            DeleteAction::make(),
        ];
    }

    /* ──────────────────────────────────────────
     │  Data mutators
     ────────────────────────────────────────── */

    /**
     * Transform stored flat blocks into Filament Builder's expected format:
     *   { type, data: { …all form fields… } }
     *
     * All custom fields (url, image, is_active, props, translations, …) are
     * preserved inside `data` so every block type loads correctly.
     *
     * Handles two DB formats gracefully:
     *  • Legacy (pre-fix): block has a 'data' key with actual field values
     *  • Current (flat):   all fields are top-level keys
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! empty($data['blocks'])) {
            $data['blocks'] = collect($data['blocks'])
                ->map(function (array $block): array {

                    // Use the 'data' wrapper if present (legacy blocks),
                    // otherwise treat the block itself as field data (current format).
                    $fieldData = (isset($block['data']) && is_array($block['data']))
                        ? $block['data']
                        : $block;

                    // Remove structural meta-keys — they are not form fields.
                    unset($fieldData['type'], $fieldData['id'], $fieldData['data']);

                    return [
                        'type' => $block['type'],
                        'data' => $fieldData,  // All form fields preserved (url, image, …)
                    ];
                })
                ->all();
        }

        return $data;
    }
}
