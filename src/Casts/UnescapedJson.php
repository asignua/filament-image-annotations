<?php

declare(strict_types=1);

namespace Asignua\FilamentImageAnnotations\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * JSON that stays readable in the database and in dumps: plain json_encode turns Cyrillic into Д…
 * Reads both forms, so rows written by anything else still load.
 *
 * @implements CastsAttributes<array<mixed>|null, array<mixed>|null>
 */
class UnescapedJson implements CastsAttributes
{
    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
