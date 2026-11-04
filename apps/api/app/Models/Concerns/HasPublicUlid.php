<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Adds a random `ulid` column used in URLs and API payloads instead of the
 * sequential primary key.
 */
trait HasPublicUlid
{
    public static function bootHasPublicUlid(): void
    {
        static::creating(function ($model) {
            $model->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
