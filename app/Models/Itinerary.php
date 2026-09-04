<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ERD entity: Itineraries
 *
 * $itinerary->type        -> the ItineraryType NAME (relation is itineraryType)
 * $itinerary->total_stops -> counted from ItineraryStops
 */
class Itinerary extends Model
{
    /*
     * Deleting an itinerary hides it, it does not erase it. The free tier is
     * three per ACCOUNT for the life of the account, so a deleted one still
     * has to be countable - see countingAgainstFreeLimit() below.
     */
    use SoftDeletes;

    protected $table = 'itineraries';

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'itinerary_type_id', 'name', 'status',
        'schedule_date', 'scheduled_date', 'notes', 'created_at', 'updated_at',
    ];

    protected $appends = ['type', 'total_stops', 'scheduled_date'];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'created_at'    => 'datetime',
            'updated_at'    => 'datetime',
            'deleted_at'    => 'datetime',
        ];
    }

    /**
     * Every itinerary a devotee has ever saved, deleted ones included.
     *
     * This is the one query that must look past the soft delete, and the one
     * definition of what "used" means - the profile, My Itineraries and the
     * limit check all read it, so they can never disagree with each other.
     */
    public static function countingAgainstFreeLimit(int $userId): int
    {
        return static::withTrashed()->where('user_id', $userId)->count();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itineraryType(): BelongsTo
    {
        return $this->belongsTo(ItineraryType::class);
    }

    public function stops(): HasMany
    {
        return $this->hasMany(ItineraryStop::class)->orderBy('stop_order');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(VisitHistory::class);
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return blank($status) ? $query : $query->where('status', $status);
    }

    /** ERD renamed this column to schedule_date; old views still read scheduled_date. */
    public function getScheduledDateAttribute()
    {
        return $this->schedule_date;
    }

    public function setScheduledDateAttribute($value): void
    {
        $this->attributes['schedule_date'] = $value;
    }

    public function getTypeAttribute(): string
    {
        return $this->itineraryType?->name ?? 'Custom';
    }

    public function getTotalStopsAttribute(): int
    {
        return $this->relationLoaded('stops')
            ? $this->stops->count()
            : $this->stops()->count();
    }

    public function visitedCount(): int
    {
        return $this->stops()->where('is_visited', true)->count();
    }

    public function progressPercent(): int
    {
        $total = $this->total_stops;

        return $total === 0 ? 0 : (int) round($this->visitedCount() / $total * 100);
    }
}
