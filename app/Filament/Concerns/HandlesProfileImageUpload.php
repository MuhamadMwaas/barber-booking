<?php

namespace App\Filament\Concerns;

use Filament\Notifications\Notification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

/**
 * Moves the avatar uploaded through the `profile_image_file` FileUpload field
 * from its temporary directory into the user's permanent profile image folder.
 *
 * The field is `dehydrated(false)`, so its value never reaches `$data` and has
 * to be read from the raw Livewire state instead.
 */
trait HandlesProfileImageUpload
{
    protected function handleProfileImageUpload(): void
    {
        $path = $this->resolveNewlyUploadedProfileImagePath();

        if ($path === null) {
            return;
        }

        $tempPath = Storage::disk('public')->path($path);

        if (! file_exists($tempPath)) {
            logger()->warning("Profile image file not found at: {$tempPath}", [
                'user_id' => $this->record->id ?? null,
            ]);

            return;
        }

        try {
            $this->record->refresh();
            $this->record->updateProfileImage(new UploadedFile(
                $tempPath,
                basename($path),
                mime_content_type($tempPath) ?: null,
                null,
                true // the file already lives on disk, skip the upload check
            ));

            @unlink($tempPath);
        } catch (\Throwable $e) {
            // Never fail the whole save because of the avatar, but make sure the
            // admin is told instead of silently ending up with the old picture.
            logger()->error('Failed to upload profile image: ' . $e->getMessage(), [
                'user_id' => $this->record->id ?? null,
                'file' => $path,
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->danger()
                ->title(__('resources.user.profile_image'))
                ->body($e->getMessage())
                ->persistent()
                ->send();
        }
    }

    /**
     * Return the path of the image that was just uploaded, or null when the
     * form still holds the picture the record already had.
     *
     * Filament keys FileUpload state by upload UUID, and a previously saved
     * path can still sit alongside the new upload, so the entry that Filament
     * just wrote into the temp directory has to be picked explicitly — taking
     * whichever element happens to come first would pick the stale one.
     */
    protected function resolveNewlyUploadedProfileImagePath(): ?string
    {
        $state = $this->form->getRawState()['profile_image_file'] ?? null;

        if (blank($state)) {
            return null;
        }

        foreach (array_reverse(Arr::wrap($state)) as $path) {
            if (is_string($path) && str_contains($path, 'temp/uploads')) {
                return $path;
            }
        }

        return null;
    }
}
