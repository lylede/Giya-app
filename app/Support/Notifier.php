<?php

namespace App\Support;

use App\Models\Church;
use App\Models\ChurchFollow;
use App\Models\DevoteePreference;
use App\Models\Favorite;
use App\Models\Feedback;
use App\Models\Notification;
use App\Models\Schedule;
use App\Models\Transaction;

/**
 * Turning things that happen into notifications.
 *
 * Until this existed the bell had no producer at all: Notification::notify()
 * was called from exactly one place in the codebase, the seeder, so every
 * notification a devotee had ever seen was demo data. An admin could add a
 * feast day and nobody was told, because nothing connected the two.
 *
 * Called explicitly from the controllers rather than hung off model events.
 * A Schedule is created by the seeder and by the CSV import as well as by the
 * admin form, and an observer cannot tell those apart - importing a hundred
 * schedules would have posted a hundred notifications to every devotee who
 * had favourited the church. The controller knows it is a person pressing
 * Save; the model does not.
 */
class Notifier
{
    /**
     * Fields a devotee would act on. A typo fixed in the notes is not worth
     * telling anyone about; a date moved is.
     */
    private const WORTH_TELLING = [
        'event_name', 'event_type', 'schedule_date',
        'start_time', 'end_time', 'is_whole_day', 'location', 'recurrence', 'status',
    ];

    /**
     * Inserted in batches rather than one row at a time. A church with eight
     * hundred favourites is eight hundred INSERTs otherwise, inside the
     * request that is trying to save the form.
     */
    private const CHUNK = 500;

    /**
     * A schedule was added or changed by an admin.
     *
     * @param  array<string, mixed>  $changed  Schedule::getChanges() on an
     *         edit; an empty array when the row is new.
     * @return int  how many devotees were told
     */
    public static function schedulePosted(Schedule $schedule, array $changed = []): int
    {
        // A draft is not an announcement. It is a half-written one.
        if ($schedule->status !== 'Published') {
            return 0;
        }

        // Nobody wants to hear about a feast day that has already passed -
        // which happens whenever an admin corrects last year's entry.
        if ($schedule->schedule_date && $schedule->schedule_date->isBefore(today())) {
            return 0;
        }

        $isNew = $changed === [];

        if (! $isNew && ! array_intersect(array_keys($changed), self::WORTH_TELLING)) {
            return 0;
        }

        $church = $schedule->church ?: Church::find($schedule->church_id);

        if (! $church) {
            return 0;
        }

        $when = $schedule->day_label;
        $body = trim("{$schedule->event_type} at {$church->name}".($when ? " \u{b7} {$when}" : ''));

        /* Posted (or moved) within a week of the event: this notification IS
           the one-week reminder, so it carries the reminder's key and
           Reminders will not add a second one. Only for a new event or a
           moved date - an "Updated: new time" must still get through. */
        $dateMoved = array_key_exists('schedule_date', $changed);
        $key = ($isNew || $dateMoved) && Reminders::isDue($schedule) ? Reminders::key($schedule) : null;

        return self::fanOut(
            self::wantingThis(self::devoteesFollowing($church->id), $schedule->event_type),
            [
                'type'       => 'schedule',
                'title'      => $schedule->event_name,
                'message'    => $isNew ? $body : "Updated: {$body}",
                'url'        => route('churches.show', $church),
                'dedupe_key' => $key,
            ]
        );
    }

    /**
     * A review was published.
     *
     * Only on the way into Approved, and only when it was something else
     * before - re-saving an approved review must not tell its author twice.
     */
    public static function feedbackApproved(Feedback $feedback, array $changed = []): bool
    {
        if ($feedback->status !== 'Approved' || ! array_key_exists('status', $changed)) {
            return false;
        }

        if (! $feedback->user_id) {
            return false;
        }

        $church = $feedback->church ?: Church::find($feedback->church_id);

        Notification::notify($feedback->user_id, 'Your review was published', [
            'type'    => 'feedback',
            'message' => $church
                ? "It is now visible on {$church->name}."
                : 'It is now visible to other devotees.',
            'url'     => $church ? route('churches.show', $church) : null,
        ]);

        return true;
    }

    /**
     * A subscription was paid for.
     *
     * Called from Transaction::settle(), which is the one place a
     * transaction's status is decided - both the Maya webhook and the return
     * from checkout go through it, so a devotee who pays and closes the tab
     * is told the same as one who waits for the redirect.
     *
     * Note what this cannot do: say anything about the subscription ENDING.
     * Premium is not stored, it is worked out on every read from a paid
     * transaction and its plan's duration - so at the moment it lapses, no
     * code runs anywhere. Nothing notices, because there is nothing to
     * notice it with. An expiry warning needs something that looks at the
     * clock, which means a scheduled command; this is the purchase only.
     */
    public static function subscriptionPaid(Transaction $transaction): bool
    {
        if ($transaction->status !== 'Paid') {
            return false;
        }

        $plan = $transaction->subscriptionPlan;
        $days = $plan?->duration_days ?? 0;

        $until = $transaction->processed_at
            ? $transaction->processed_at->copy()->addDays($days)
            : null;

        Notification::notify($transaction->user_id, 'Premium is active', [
            'type'    => 'system',
            'message' => $until
                ? "{$transaction->plan}, until {$until->format('M j, Y')}."
                : $transaction->plan,
            'url'     => route('profile'),
        ]);

        return true;
    }

    /**
     * Who has said they care about this church.
     *
     * Favourites, reused. They are presented as saved places rather than as a
     * subscription, and the two are not quite the same thing - but a devotee
     * who saved a church is the closest the schema has to one who wants to
     * hear about it, and asking them to opt in separately would mean a list
     * nobody had joined yet.
     *
     * @return array<int>
     */
    private static function devoteesFollowing(int $churchId): array
    {
        // "Notify me" on the church page, with saved churches on by default.
        return ChurchFollow::followersOf($churchId);
    }

    /**
     * Drop devotees who switched this kind of event off in their profile
     * (Feast Day Alerts, Mass Schedule Reminders).
     *
     * @param  array<int>  $userIds
     * @return array<int>
     */
    private static function wantingThis(array $userIds, ?string $eventType): array
    {
        $column = match (strtolower((string) $eventType)) {
            'feast day' => 'notify_feast_day',
            'mass'      => 'notify_mass_schedule',
            default     => null,
        };

        if ($column === null || $userIds === []) {
            return $userIds;
        }

        $off = DevoteePreference::whereIn('user_id', $userIds)->where($column, false)->pluck('user_id')->all();

        return array_values(array_diff($userIds, $off));
    }

    /**
     * @param  array<int>  $userIds
     * @param  array<string, mixed>  $row
     */
    private static function fanOut(array $userIds, array $row): int
    {
        if ($userIds === []) {
            return 0;
        }

        $now = now();
        $row += ['dedupe_key' => null];

        foreach (array_chunk($userIds, self::CHUNK) as $chunk) {
            $rows = array_map(fn (int $id) => $row + [
                'user_id'    => $id,
                'is_read'    => false,
                'created_at' => $now,
            ], $chunk);

            // With a key, someone who already has it is skipped, not an error.
            $row['dedupe_key'] === null
                ? Notification::insert($rows)
                : Notification::insertOrIgnore($rows);
        }

        return count($userIds);
    }
}
