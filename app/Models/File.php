<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

class File extends Model
{


    protected $fillable = [
        'instance_type',
        'instance_id',
        'name',
        'path',
        'disk',
        'type',
        'key',
        'extension',
        'group',
        'sort_order',
    ];

    public static function rules()
    {
        return [
            'instance_type' => 'required|string|max:255',
            'instance_id' => 'required|integer',
            'name' => 'required|string|max:255',
            'path' => 'required|string|max:255',
            'disk' => 'required|string|max:255',
            'type' => 'nullable|string|max:75',
            'key' => 'nullable|string|max:255',
            'extension' => 'nullable|string|max:10',
            'group' => 'nullable|string|max:255',
        ];
    }

    public function fileable(): MorphTo
    {
        return $this->morphTo('fileable', 'instance_type', 'instance_id');
    }

    // public function UrlFile()
    // {
    //     return Storage::disk($this->disk)->url($this->path);
    // }

    public function urlFile(): string
    {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($this->disk);
        $url = $storage->url($this->path);

        // Some files keep a fixed name when replaced (service images are stored as
        // "<service name>_<id>.<ext>"), so the URL of a new image is identical to
        // the old one. Nginx sends no Cache-Control for these, which lets the
        // browser serve its cached copy without revalidating — the new picture
        // shows up on the edit page but the list keeps the old one. Tying the URL
        // to the record's timestamp gives every replacement a fresh address.
        if ($this->updated_at) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $this->updated_at->timestamp;
        }

        return $url;
    }
    public static function booted()
    {
        static::deleting(function ($record) {
            if (file_exists(Storage::disk($record->disk)->path($record->path))) {
                Storage::disk($record->disk)->delete($record->path);
            }
        });
    }
}
