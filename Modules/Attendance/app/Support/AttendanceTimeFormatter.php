<?php

namespace Modules\Attendance\app\Support;

use Carbon\Carbon;

class AttendanceTimeFormatter
{
    public static function timezone(): string
    {
        return config('app.timezone', 'Asia/Dhaka');
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    public static function parse(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->timezone(self::timezone());
        }

        return Carbon::parse($value, self::timezone());
    }

    public static function toAppTimezone(?Carbon $value): ?Carbon
    {
        return $value?->copy()->timezone(self::timezone());
    }

    public static function formatHi(?Carbon $value): ?string
    {
        return self::toAppTimezone($value)?->format('H:i');
    }

    public static function formatTime12(?Carbon $value): ?string
    {
        return self::toAppTimezone($value)?->format('h:i A');
    }

    public static function formatDateTime(?Carbon $value): ?string
    {
        return self::toAppTimezone($value)?->format('Y-m-d H:i:s');
    }

    /** API-safe datetime string in app timezone (no UTC Z suffix confusion). */
    public static function formatForApi(?Carbon $value): ?string
    {
        return self::formatDateTime($value);
    }

    public static function appendTimeFields(array $data, ?Carbon $checkIn = null, ?Carbon $checkOut = null): array
    {
        if ($checkIn) {
            $data['check_in'] = self::formatHi($checkIn);
            $data['check_in_display'] = self::formatTime12($checkIn);
            $data['check_in_at'] = self::formatForApi($checkIn);
        }

        if ($checkOut) {
            $data['check_out'] = self::formatHi($checkOut);
            $data['check_out_display'] = self::formatTime12($checkOut);
            $data['check_out_at'] = self::formatForApi($checkOut);
        }

        return $data;
    }
}
