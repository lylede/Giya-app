<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
        'is_recurring', 'recurrence', 'notes', 'created_at', 'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'is_recurring'  => 'boolean',
            'is_whole_day'  => 'boolean',
            'created_at'    => 'datetime',
        ];
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /** "Monday" for a recurring service, or the date for a one-off. */
    public function getDayLabelAttribute(): string
    {
        if ($this->is_recurring) {
            return $this->recurrence ?: 'Recurring';
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
