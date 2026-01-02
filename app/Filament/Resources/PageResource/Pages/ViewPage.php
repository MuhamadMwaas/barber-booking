<?php

namespace App\Filament\Resources\PageResource\Pages;

use App\Filament\Resources\PageResource;
use App\Services\PageRenderService;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPage extends ViewRecord
{
    protected static string $resource = PageResource::class;

    protected string $view = 'filament.resources.pages.view-page';

    public function getTitle(): string|Htmlable
    {
        return __('resources.page_resource.preview') . ': ' . $this->record->page_key;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label(__('resources.page_resource.edit'))
                ->icon('heroicon-o-pencil'),
        ];
    }

    public function getPageContent(): string
    {
        try {
            $pageKey = $this->record->page_key;
            $lang = app()->getLocale();

            $service = new PageRenderService();
            $view = $service->render($pageKey, $lang);

            return $view->render();
        } catch (\Exception $e) {
            return '<div class="p-8 text-center text-red-600">
                <p class="text-lg font-semibold">' . __('resources.page_resource.preview_error') . '</p>
                <p class="mt-2 text-sm">' . $e->getMessage() . '</p>
            </div>';
        }
    }
}
