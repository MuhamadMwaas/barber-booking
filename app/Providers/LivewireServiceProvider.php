<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class LivewireServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Override Livewire's file upload controller to use shorter filenames
        // This fixes issues with long Arabic filenames on Windows
        // $this->app->bind(
        //     FileUploadController::class,
        //     \App\Services\CustomFileUploadController::class
        // );

        // // Also bind our custom TemporaryUploadedFile to handle the shorter names
        // $this->app->bind(
        //     TemporaryUploadedFile::class,
        //     \App\Services\CustomTemporaryUploadedFile::class
        // );
    }
}
