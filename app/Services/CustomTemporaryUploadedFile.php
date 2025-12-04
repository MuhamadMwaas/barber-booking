<?php

namespace App\Services;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Illuminate\Support\Str;

class CustomTemporaryUploadedFile extends TemporaryUploadedFile
{
    /**
     * Override the method to generate shorter filenames
     */
    public static function generateHashNameWithOriginalNameEmbedded($file)
    {
        $hash = Str::random(30);
        // Store only a short hash of the original filename instead of base64 encoding it
        $nameHash = substr(md5($file->getClientOriginalName()), 0, 10);
        $meta = '-meta' . $nameHash . '-';
        $extension = '.' . $file->getClientOriginalExtension();

        return $hash . $meta . $extension;
    }

    /**
     * Since we changed how we encode the filename, we need to update extraction
     * For our short hash format, we can't extract the original name
     * So we return a placeholder
     */
    public function extractOriginalNameFromFilePath($path)
    {
        // Try the new format first (short hash)
        if (preg_match('/-meta([a-f0-9]{10})-/', $path, $matches)) {
            // We can't recover the original name from the hash
            // Return a generic name with the correct extension
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            return 'file.' . $extension;
        }

        // Fall back to the original method for old files
        try {
            return base64_decode(head(explode('-', last(explode('-meta', str($path)->replace('_', '/'))))));
        } catch (\Exception $e) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
            return 'file.' . $extension;
        }
    }
}
