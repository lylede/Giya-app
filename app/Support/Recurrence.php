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
 *   ['type' => 'yearly',  'month' => 1, 'day' => 15]            every January 15
 *   ['type' => 'yearly',  'easter' => 0]                        Easter Sunday
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
    public static function fromInput(?string $type, array $days = [], $nth = null, $weekday = null, $month = null,
                                     ?string $yearlyOn = null, $day = null): ?array
    {
        // Yearly has three kinds: Nth weekday (default), a fixed date, Easter.
        if ($type === 'yearly' && $yearlyOn === 'date') {
            return isset(self::MONTHS[(int) $month]) && self::validDay((int) $month, $day)
                ? ['type' => 'yearly', 'month' => (int) $month, 'day' => (int) $day] : null;
        }
        if ($type === 'yearly' && $yearlyOn === 'easter') {
            return ['type' => 'yearly', 'easter' => 0];
        }

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
            'yearly'  => match (true) {
                isset($rule['easter']) => 'Yearly - Easter Sunday',
                isset($rule['day'])    => 'Yearly - '.self::MONTHS[$rule['month']].' '.$rule['day'],
                default                => 'Yearly - '.self::NTH[$rule['nth']].' '.self::WEEKDAYS[$rule['weekday']]
                                          .' of '.self::MONTHS[$rule['month']],
            },
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
                $date = match (true) {
                    isset($rule['easter']) => self::easter($year)->addDays((int) $rule['easter']),
                    isset($rule['day'])    => self::fixedDate($year, $rule['month'], $rule['day']),
                    default                => self::nthWeekday($year, $rule['month'], $rule['nth'], $rule['weekday']),
                };
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

    /** Easter Sunday (Gregorian, the "anonymous" algorithm - no PHP extension needed). */
    public static function easter(int $year): Carbon
    {
        $a = $year % 19; $b = intdiv($year, 100); $c = $year % 100;
        $d = intdiv($b, 4); $e = $b % 4; $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    /** A fixed date; Feb 29 falls back to Feb 28 in other years. */
    private static function fixedDate(int $year, int $month, int $day): Carbon
    {
        return Carbon::create($year, $month, 1)->startOfDay()
            ->day(min($day, Carbon::create($year, $month, 1)->daysInMonth));
    }

    private static function validDay(int $month, $day): bool
    {
        return is_numeric($day) && (int) $day >= 1
            && (int) $day <= Carbon::create(2024, max(1, min(12, $month)), 1)->daysInMonth;
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
