<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * How a schedule repeats, and the dates that follows from it.
 *
 * A rule is a small array, stored as JSON on the schedule:
 *   ['type' => 'daily']
 *   ['type' => 'weekly',  'days' => [1, 3, 5]]                  Mon, Wed, Fri
 *   ['type' => 'monthly', 'nth' => 1, 'weekday' => 5]           1st Friday
 *   ['type' => 'yearly',  'nth' => 3, 'weekday' => 0, 'month' => 1]
 *                                                    3rd Sunday of January
 * Weekdays are 0 = Sunday ... 6 = Saturday (as Carbon counts them).
 * nth is 1-4, or -1 for "last".
 *
 * Monthly and yearly rules have a next date, which the app keeps in
 * schedule_date so lists, Giya AI and reminders can sort and count days
 * the same way they do for a one-off event.
 */
final class Recurrence
{
    public const TYPES = ['daily', 'weekly', 'monthly', 'yearly'];

    public const WEEKDAYS = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
                             4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

    public const NTH = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', -1 => 'Last'];

    public const MONTHS = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                           5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                           9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];

    /**
     * Build a rule from the form, or null for "does not repeat".
     * Returns null too when a required part is missing.
     */
    public static function fromInput(?string $type, array $days = [], $nth = null, $weekday = null, $month = null): ?array
    {
        return match ($type) {
            'daily'   => ['type' => 'daily'],
            'weekly'  => ($d = self::cleanDays($days)) ? ['type' => 'weekly', 'days' => $d] : null,
            'monthly' => self::validNth($nth) && self::validWeekday($weekday)
                ? ['type' => 'monthly', 'nth' => (int) $nth, 'weekday' => (int) $weekday] : null,
            'yearly'  => self::validNth($nth) && self::validWeekday($weekday) && isset(self::MONTHS[(int) $month])
                ? ['type' => 'yearly', 'nth' => (int) $nth, 'weekday' => (int) $weekday, 'month' => (int) $month] : null,
            default   => null,
        };
    }

    /** "Daily", "Weekly (Monday, Wednesday)", "Monthly - 1st Friday", "Yearly - 3rd Sunday of January" */
    public static function label(array $rule): string
    {
        return match ($rule['type']) {
            'daily'   => 'Daily',
            'weekly'  => 'Weekly ('.implode(', ', array_map(fn ($d) => self::WEEKDAYS[$d], $rule['days'])).')',
            'monthly' => 'Monthly - '.self::NTH[$rule['nth']].' '.self::WEEKDAYS[$rule['weekday']],
            'yearly'  => 'Yearly - '.self::NTH[$rule['nth']].' '.self::WEEKDAYS[$rule['weekday']]
                         .' of '.self::MONTHS[$rule['month']],
        };
    }

    /** Monthly and yearly rules have dates; daily and weekly do not need one. */
    public static function hasDates(?array $rule): bool
    {
        return in_array($rule['type'] ?? null, ['monthly', 'yearly'], true);
    }

    /** The first date on or after $from that the rule falls on (monthly/yearly). */
    public static function next(array $rule, ?Carbon $from = null): ?Carbon
    {
        $from = ($from ?? today())->copy()->startOfDay();

        if ($rule['type'] === 'yearly') {
            for ($year = $from->year; $year <= $from->year + 1; $year++) {
                $date = self::nthWeekday($year, $rule['month'], $rule['nth'], $rule['weekday']);
                if ($date->gte($from)) {
                    return $date;
                }
            }
        }

        if ($rule['type'] === 'monthly') {
            $month = $from->copy()->startOfMonth();
            for ($i = 0; $i < 2; $i++, $month->addMonth()) {
                $date = self::nthWeekday($month->year, $month->month, $rule['nth'], $rule['weekday']);
                if ($date->gte($from)) {
                    return $date;
                }
            }
        }

        return null;
    }

    /** e.g. the 3rd Sunday (weekday 0) of January 2027, or the last Friday. */
    public static function nthWeekday(int $year, int $month, int $nth, int $weekday): Carbon
    {
        if ($nth === -1) {
            $date = Carbon::create($year, $month, 1)->endOfMonth()->startOfDay();
            while ($date->dayOfWeek !== $weekday) {
                $date->subDay();
            }

            return $date;
        }

        $date = Carbon::create($year, $month, 1)->startOfDay();
        while ($date->dayOfWeek !== $weekday) {
            $date->addDay();
        }

        return $date->addWeeks($nth - 1);
    }

    private static function cleanDays(array $days): array
    {
        $days = array_values(array_unique(array_map('intval', array_filter($days, fn ($d) => is_numeric($d)))));
        $days = array_values(array_filter($days, fn ($d) => isset(self::WEEKDAYS[$d])));
        // Monday first, Sunday last - the way a parish bulletin lists them.
        usort($days, fn ($a, $b) => (($a + 6) % 7) <=> (($b + 6) % 7));

        return $days;
    }

    private static function validNth($nth): bool
    {
        return is_numeric($nth) && isset(self::NTH[(int) $nth]);
    }

    private static function validWeekday($weekday): bool
    {
        return is_numeric($weekday) && isset(self::WEEKDAYS[(int) $weekday]);
    }
}
