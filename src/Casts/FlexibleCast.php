<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Casts\Json;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a JSON column to/from a flat array of block items.
 * Each item has a `_type` key plus flat field values.
 */
class FlexibleCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, array<string, mixed>>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if (! isset($attributes[$key])) {
            return null;
        }

        $data = Json::decode($attributes[$key]);

        return is_array($data) ? $data : null;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $value
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => Json::encode($value)];
    }
}
