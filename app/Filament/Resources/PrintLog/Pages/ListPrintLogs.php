<?php

namespace App\Filament\Resources\PrintLog\Pages;

use App\Filament\Resources\PrintLog\PrintLogResource;
use App\Services\Print\PrintService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Tabs\Tab;

class ListPrintLogs extends ListRecords {
    protected static string $resource = PrintLogResource::class;

    protected function getHeaderActions(): array {
        return [
            Actions\Action::make('statistics')
                ->label('Statistics')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalHeading('Print Statistics')
                ->modalContent(function () {
                    $printService = app(PrintService::class);
                    $stats = $printService->getStatistics();

                    return view('filament.modals.print-statistics', compact('stats'));
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close'),
        ];
    }

    public function getTabs(): array {
        return [
            'all' => Tab::make('All')
                ->badge(fn() => $this->getModel()::count()),

            'today' => Tab::make('Today')
                ->badge(fn() => $this->getModel()::whereDate('created_at', today())->count())
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('created_at', today())),

            'success' => Tab::make('Successful')
                ->badge(fn() => $this->getModel()::where('status', 'success')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'success')),

            'failed' => Tab::make('Failed')
                ->badge(fn() => $this->getModel()::where('status', 'failed')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'failed')),

            'copies' => Tab::make('Copies')
                ->badge(fn() => $this->getModel()::where('print_type', 'copy')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('print_type', 'copy')),
        ];
    }
}
