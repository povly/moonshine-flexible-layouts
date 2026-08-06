<?php

declare(strict_types=1);

namespace Povly\FlexibleLayouts\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class FlexibleCast implements CastsAttributes
{
    /**
     * Maximum accepted JSON nesting depth. Real-world page-builder content rarely
     * exceeds depth 5-10. Cap at 64 to prevent recursion-based DoS payloads.
     */
    private const JSON_DEPTH_LIMIT = 64;

    /**
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
            $decoded = json_decode($value, true, self::JSON_DEPTH_LIMIT, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException $e) {
            // Decode failures always log at error level — indicates real data
            // corruption (tampering, malformed JSON, DoS attempt via depth > 64).
            // Not gated by config('flexible-layouts.logging') because silent
            // corruption is worse than prod noise.
            Log::error('[FlexibleCast] get() failed to decode JSON', [
                'model' => $model::class,
                'key' => $key,
                'error' => $e->getMessage(),
                'depth_limit' => self::JSON_DEPTH_LIMIT,
            ]);

            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $value
     * @param  array<string, mixed>  $attributes
     * @return array<string, string|null> Returns [$key => $encoded] per CastsAttributes contract.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === []) {
            return [$key => null];
        }

        if ($value instanceof Collection) {
            $value = $value->toArray();
        }

        try {
            return [$key => json_encode(array_values($value), JSON_THROW_ON_ERROR)];
        } catch (\JsonException $e) {
            // Encode failures indicate a programming bug (non-encodable data
            // assigned to the cast attribute). Always log at error level.
            Log::error('[FlexibleCast] set() failed to encode JSON', [
                'model' => $model::class,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return [$key => null];
        }
    }
}
