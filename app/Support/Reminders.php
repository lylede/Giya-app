<?php

namespace App\Support;

use App\Models\ChurchFollow;
use App\Models\DevoteePreference;
use App\Models\Notification;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * "It's in a week": reminders for dated events at churches a devotee follows.
 *
 * How they are delivered, and why this way:
 * GIYA runs on Vercel's free plan, where nothing runs between visits - there
 * is no always-on server for a nightly job. So reminders are created when the
 * devotee shows up: on any page they open, and on the bell's 90-second check.
 * Whatever is due for them is added then. Someone who does not open GIYA for a
 * week is not missing anything they could have seen.
 *
 * Each reminder carries a dedupe key ("reminder:<schedule>:<date>") and the
 * notifications table has a unique index on (user, key), so a reminder can
 * never arrive twice - not on the next page, not from two tabs at once. If an
 * admin moves the date, the key changes and a fresh reminder is due.
 *
 * Only dated, one-off events get reminders (feast days, novenas, special
 * Masses). A weekly Mass would otherwise remind everyone every week.
 */
final class Reminders
{
    /** Remind this many days ahead. */
    public const DAYS_BEFORE = 7;

    /** Check a devotee at most this often (the bell polls every 90s). */
    private const THROTTLE_MINUTES = 5;

    /** Run the check unless it already ran for this devotee in the last few minutes. */
    public static function deliverIfDue(User $user): int
    {
        if (! Cache::add('giya:reminders:'.$user->id, true, now()->addMinutes(self::THROTTLE_MINUTES))) {
            return 0;
        }

        return self::deliverFor($user);
    }

    /**
     * Add every reminder now due for this devotee.
     *
     * @return int how many were added
     */
    public static function deliverFor(User $user): int
    {
        $churchIds = ChurchFollow::churchIdsFor($user->id);

        if ($churchIds === []) {
            return 0;
        }

        $prefs = DevoteePreference::where('user_id', $user->id)->first();

        $rows = Schedule::with('church')
            ->whereIn('church_id', $churchIds)
            ->where('status', 'Published')
            ->where(fn ($q) => $q->where('is_recurring', false)->orWhereNull('is_recurring'))
            ->whereNotNull('schedule_date')
            // whereDate, not whereBetween: a date stored as "2027-01-20 00:00:00"
            // sorts after the string "2027-01-20", so the last day of the
            // window was silently skipped.
            ->whereDate('schedule_date', '>=', today()->toDateString())
            ->whereDate('schedule_date', '<=', today()->addDays(self::DAYS_BEFORE)->toDateString())
            ->get()
            ->filter(fn (Schedule $s) => $s->church && $s->church->is_active)
            ->filter(fn (Schedule $s) => self::wants($prefs, $s->event_type))
            ->map(fn (Schedule $s) => [
                'user_id'    => $user->id,
                'type'       => 'schedule',
                'title'      => $s->event_name,
                'message'    => self::message($s),
                'url'        => route('churches.show', $s->church),
                'dedupe_key' => self::key($s),
                'is_read'    => false,
                'created_at' => now(),
            ])
            ->values()
            ->all();

        return $rows === [] ? 0 : Notification::insertOrIgnore($rows);
    }

    /** Would this schedule get a reminder right now? */
    public static function isDue(Schedule $schedule): bool
    {
        return $schedule->status === 'Published'
            && ! $schedule->is_recurring
            && $schedule->schedule_date
            && $schedule->schedule_date->betweenIncluded(today(), today()->addDays(self::DAYS_BEFORE));
    }

    public static function key(Schedule $schedule): string
    {
        return 'reminder:'.$schedule->id.':'.$schedule->schedule_date->toDateString();
    }

    /**
     * Has the devotee switched this kind of event off in their profile?
     * No preferences row means the defaults, which are all on.
     */
    public static function wants(?DevoteePreference $prefs, ?string $eventType): bool
    {
        if (! $prefs) {
            return true;
        }

        return match (strtolower((string) $eventType)) {
            'feast day' => (bool) $prefs->notify_feast_day,
            'mass'      => (bool) $prefs->notify_mass_schedule,
            default     => true,
        };
    }

    /** "Feast Day at Basilica del Santo Niño · in 7 days (Sun, Jan 18)" */
    public static function message(Schedule $schedule): string
    {
        $days = (int) today()->diffInDays($schedule->schedule_date->copy()->startOfDay(), false);

        $when = match (true) {
            $days <= 0 => 'today',
            $days === 1 => 'tomorrow',
            default => "in {$days} days",
        };

        $time = $schedule->start_time ? ' at '.Carbon::parse($schedule->start_time)->format('g:i A') : '';

        return "{$schedule->event_type} at {$schedule->church->name} \u{b7} {$when} ("
            .$schedule->schedule_date->format('D, M j').$time.')';
    }
}
