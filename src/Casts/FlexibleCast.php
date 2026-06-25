<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class FlexibleCast implements CastsAttributes
{
    /**
     * @param  Model  $model
     * @param  string|null  $value  JSON string from the database
     * @param  array<string, mixed>  $attributes
     * @return array<int, array<string, mixed>>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException) {
            return null;
        }
    }

    /**
     * @param  Model  $model
     * @param  array<int, array<string, mixed>>|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === []) {
            return null;
        }

        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        try {
            return json_encode(array_values($value), JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
