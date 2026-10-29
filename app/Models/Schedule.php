<?php

namespace App\Models;

use App\Support\Recurrence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ERD entity: Schedules */
class Schedule extends Model
{
    protected $table = 'schedules';

    public $timestamps = false;

    protected $fillable = [
        'church_id', 'event_name', 'event_type', 'time_frame_label', 'schedule_date',
        'start_time', 'end_time', 'is_whole_day', 'location', 'status',
        'is_recurring', 'recurrence', 'recurrence_rule', 'notes', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'is_recurring'  => 'boolean',
            'recurrence_rule' => 'array',
            'is_whole_day'  => 'boolean',
            'created_at'    => 'datetime',
        ];
    }

    /** Yearly festivals get the week-before reminder, every year. */
    public function isYearly(): bool
    {
        return ($this->recurrence_rule['type'] ?? null) === 'yearly';
    }

    /**
     * Move monthly and yearly schedules whose date has passed on to their
     * next date (Sinulog 2027 -> Sinulog 2028). GIYA has no nightly job on
     * the free hosting, so this runs where the dates are read - the Home
     * page, the admin list and the reminder check - at most once a day.
     */
    public static function rollForwardDue(): void
    {
        if (! Cache::add('giya:schedules:rolled:'.today()->toDateString(), true, now()->endOfDay())) {
            return;
        }

        static::query()
            ->whereNotNull('recurrence_rule')
            ->where(fn ($q) => $q->whereNull('schedule_date')->orWhereDate('schedule_date', '<', today()->toDateString()))
            ->get()
            ->filter(fn (Schedule $s) => Recurrence::hasDates($s->recurrence_rule))
            ->each(function (Schedule $s) {
                $s->schedule_date = Recurrence::next($s->recurrence_rule);
                $s->saveQuietly();
            });
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /**
     * "Monday" for a recurring service, the date for a one-off, and for a
     * monthly or yearly one both: "Yearly - 4th Sunday of January (Jan 24, 2027)".
     */
    public function getDayLabelAttribute(): string
    {
        if ($this->is_recurring) {
            $label = $this->recurrence ?: 'Recurring';

            return Recurrence::hasDates($this->recurrence_rule) && $this->schedule_date
                ? $label.' ('.$this->schedule_date->format('M j, Y').')'
                : $label;
        }

        return $this->schedule_date?->format('M j, Y') ?? '-';
    }

    public function timeLabel(?string $value): string
    {
        return $value ? date('g:i A', strtotime($value)) : '-';
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereNull('schedule_date')
            ->orWhere('schedule_date', '>=', now()->toDateString()))
            ->orderBy('schedule_date')
            ->orderBy('start_time');
    }
}
