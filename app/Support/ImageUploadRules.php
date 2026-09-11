<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Dimensions;
use Illuminate\Validation\Rules\File;

/**
 * The single source of truth for what counts as an acceptable profile image
 * (AUTH-06).
 *
 * There are three separate entry points for an avatar — the customer API, the
 * admin user form and the provider form — and a whitelist that only holds on
 * one of them is not a whitelist. Every constraint here is read from
 * `config/uploads.php`, so tightening a limit is one edit, not three.
 *
 * On why `max:` alone was never enough: file size bounds the bytes that arrive,
 * not the pixels they expand into. `dimensions` is the rule that bounds the
 * decode, and it is cheap — getimagesize() reads the header and stops.
 */
final class ImageUploadRules
{
    /**
     * Validation rules for a profile image field.
     *
     * @param  string  $presence  'sometimes' for a partial update, 'required'
     *                            when the image must be supplied.
     * @return array<int, mixed>
     */
    public static function profileImage(string $presence = 'sometimes'): array
    {
        return [
            $presence,
            File::image()
                ->types(self::config('mimes'))
                ->max(self::maxKilobytes())
                ->dimensions(self::dimensions()),
        ];
    }

    /**
     * The dimension ceiling on its own, for callers that assemble their own
     * rule list (Filament's FileUpload takes plain rules, not a File object).
     */
    public static function dimensions(): Dimensions
    {
        return Rule::dimensions()
            ->maxWidth((int) self::config('max_width'))
            ->maxHeight((int) self::config('max_height'));
    }

    /**
     * @return array<int, string>
     */
    public static function mimeTypes(): array
    {
        return self::config('mime_types');
    }

    public static function maxKilobytes(): int
    {
        return (int) self::config('max_kilobytes');
    }

    /**
     * The extension to store an already-validated image under.
     *
     * Never derive a stored filename from getClientOriginalExtension(): that
     * string is chosen by whoever sent the request, and these files land on the
     * public disk. Deriving it from the file's own bytes and then checking it
     * against the same whitelist the validator used means the name on disk can
     * only ever be one of the formats we accept.
     *
     * @throws \InvalidArgumentException when the file is not an accepted image.
     */
    public static function safeExtension(UploadedFile $image): string
    {
        $extension = strtolower((string) $image->extension());

        if (! in_array($extension, self::config('mimes'), true)) {
            throw new \InvalidArgumentException(
                "Refusing to store an image with an unaccepted type [{$extension}]."
            );
        }

        return $extension;
    }

    private static function config(string $key): mixed
    {
        return config("uploads.profile_image.{$key}");
    }
}
