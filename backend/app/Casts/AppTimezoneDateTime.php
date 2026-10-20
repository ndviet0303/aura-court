<?php

namespace App\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Wall-clock datetime stored in the app timezone. Input that carries an offset (ISO "...Z" from the
 * browser) is converted to the app timezone first, where the built-in cast would store its clock as-is.
 */
class AppTimezoneDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value, config('app.timezone'));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $time = $value instanceof \DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse($value, config('app.timezone'));

        return $time->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
