<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Illuminate\Support\Str;

class CustomFileUploadController extends FileUploadController
{
    public function validateAndStore($files, $disk)
    {
        Validator::make(['files' => $files], [
            'files.*' => FileUploadConfiguration::rules()
        ])->validate();

        $fileHashPaths = collect($files)->map(function ($file) use ($disk) {
            // Use a shorter filename instead of embedding the full original name
            $filename = $this->generateShortHashName($file);

            return $file->storeAs('/' . FileUploadConfiguration::path(), $filename, [
                'disk' => $disk
            ]);
        });

        // Strip out the temporary upload directory from the paths.
        return $fileHashPaths->map(function ($path) {
            return str_replace(FileUploadConfiguration::path('/'), '', $path);
        });
    }

    /**
     * Generate a shorter hash name for uploaded files
     * This prevents "file path too long" errors on Windows with Arabic filenames
     */
    protected function generateShortHashName($file): string
    {
        $hash = Str::random(30);
        // Store only a short hash of the original filename instead of base64 encoding it
        // This keeps filenames short while still being unique
        $nameHash = substr(md5($file->getClientOriginalName()), 0, 10);
        $meta = '-meta' . $nameHash . '-';
        $extension = '.' . $file->getClientOriginalExtension();

        return $hash . $meta . $extension;
    }
}
