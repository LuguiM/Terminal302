<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class StorageUrl
{
    public static function for(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk(config('filesystems.default'));

        if (config('filesystems.default') === 's3') {
            return $disk->temporaryUrl($path, now()->addMinutes(15));
        }

        return $disk->url($path);
    }
}
