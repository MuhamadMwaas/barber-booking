<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        return $storage->url($this->path);
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
