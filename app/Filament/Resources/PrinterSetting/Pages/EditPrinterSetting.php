<?php

namespace App\Filament\Resources\PrinterSetting\Pages;

use App\Filament\Resources\PrinterSetting\PrinterSettingResource;
use App\Services\Print\PrintService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPrinterSetting extends EditRecord {
    protected static string $resource = PrinterSettingResource::class;

    protected function getHeaderActions(): array {
        return [
            Actions\Action::make('test')
                ->label('Test Printer')
                ->icon('heroicon-o-play')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Test Printer Connection')
                ->modalDescription('This will test the printer connection and save the results.')
                ->action(function (): void {
                    $printService = app(PrintService::class);
                    $result = $printService->testPrinter($this->record);

                    if ($result['success']) {
                        \Filament\Notifications\Notification::make()
                            ->title('Printer Test Successful')
                            ->success()
                            ->body($result['message'])
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Printer Test Failed')
                            ->danger()
                            ->body($result['message'])
                            ->send();
                    }

                    $this->refreshFormData([
                        'last_test_at',
                        'last_test_status',
                        'last_test_message',
                    ]);
                }),

            Actions\Action::make('set_default')
                ->label('Set as Default')
                ->icon('heroicon-o-star')
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->setAsDefault();

                    \Filament\Notifications\Notification::make()
                        ->title('Default Printer Updated')
                        ->success()
                        ->body($this->record->name . ' is now the default printer.')
                        ->send();

                    $this->refreshFormData(['is_default']);
                })
                ->visible(fn(): bool => ! $this->record->is_default),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string {
        return 'Printer settings updated';
    }
}
