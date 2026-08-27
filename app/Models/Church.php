<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * ERD entity: Churches
 *
 * Three former columns are now derived, so existing Blade keeps working:
 *   $church->category      -> the category NAME (relation is churchCategory)
 *   $church->image_url     -> the primary ChurchImages row
 *   $church->rating        -> average of approved Feedback
 *   $church->daily_visits  -> VisitHistory count over the last 30 days
 *   $church->mass_schedule -> recurring rows from Schedules
 */
class Church extends Model
{
    protected $table = 'churches';

    public $timestamps = false;

    protected $fillable = [
        'category_id', 'name', 'location', 'address', 'description',
        'latitude', 'longitude', 'opening_time', 'closing_time',
        'is_featured', 'is_active', 'created_at', 'updated_at',
    ];

    protected $appends = ['category', 'image_url', 'rating', 'daily_visits'];

    protected function casts(): array
    {
        return [
            'latitude'    => 'float',
            'longitude'   => 'float',
            'is_featured' => 'boolean',
            'is_active'   => 'boolean',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }

    /* ------------------------------ Relationships --------------------------- */

    /** Named churchCategory so the `category` accessor can stay a string. */
    public function churchCategory(): BelongsTo
    {
        return $this->belongsTo(ChurchCategory::class, 'category_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ChurchImage::class);
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ChurchImage::class)->where('is_primary', true);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(VisitHistory::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function itineraryStops(): HasMany
    {
        return $this->hasMany(ItineraryStop::class);
    }

    /* --------------------------------- Scopes ------------------------------- */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $term = '%'.mb_strtolower($term).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw('LOWER(name) LIKE ?', [$term])
            ->orWhereRaw('LOWER(location) LIKE ?', [$term]));
    }

    /**
     * Sort by average approved rating. `rating` is no longer a column, so a
     * plain orderBy('rating') would fail - this adds the average as a computed
     * column the database can actually sort on.
     */
    public function scopeOrderByRating(Builder $query, string $direction = 'desc'): Builder
    {
        return $query
            ->withAvg(['feedback as avg_rating' => fn ($q) => $q->where('status', 'Approved')], 'rating')
            ->orderByRaw('avg_rating '.($direction === 'asc' ? 'asc NULLS FIRST' : 'desc NULLS LAST'));
    }

    /** Sort by how many visits were logged in the last 30 days. */
    public function scopeOrderByPopularity(Builder $query): Builder
    {
        return $query
            ->withCount(['visits as recent_visits' => fn ($q) => $q->where('visited_at', '>=', now()->subDays(30))])
            ->orderByDesc('recent_visits');
    }

    /** Filter by category NAME, keeping old controller calls readable. */
    public function scopeOfCategory(Builder $query, ?string $name): Builder
    {
        if (blank($name) || $name === 'All') {
            return $query;
        }

        return $query->whereHas('churchCategory', fn ($q) => $q->where('name', $name));
    }

    /* ------------------- Derived attributes (were columns) ------------------- */

    public function getCategoryAttribute(): string
    {
        return $this->churchCategory?->name ?? 'Church';
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->primaryImage?->image_url
            ?? $this->images->first()?->image_url;
    }

    public function getRatingAttribute(): float
    {
        return round((float) $this->feedback()
            ->where('status', 'Approved')
            ->whereNotNull('rating')
            ->avg('rating'), 2);
    }

    public function getDailyVisitsAttribute(): int
    {
        return $this->visits()
            ->where('visited_at', '>=', now()->subDays(30))
            ->count();
    }

    /** Recurring schedule rows, shaped like the old jsonb column. */
    public function getMassScheduleAttribute(): array
    {
        return $this->schedules()
            ->where('is_recurring', true)
            ->orderBy('start_time')
            ->get()
            ->map(fn (Schedule $s) => [
                'day'   => $s->recurrence,
                'time'  => $s->start_time,
                'title' => $s->event_name,
            ])
            ->all();
    }

    /* -------------------------------- Display ------------------------------- */

    public const CATEGORY_COLORS = [
        'Basilica'  => '#8E3B2F',
        'Cathedral' => '#8E3B2F',
        'Shrine'    => '#D7A94A',
        'Church'    => '#4A90D9',
        'Chapel'    => '#9B6B4A',
        'Heritage'  => '#6B7280',
    ];

    /** "5:00 AM - 8:00 PM", or a fallback when hours are not set. */
    public function getHoursLabelAttribute(): string
    {
        if (! $this->opening_time || ! $this->closing_time) {
            return 'Hours not listed';
        }

        return date('g:i A', strtotime($this->opening_time))
             . ' - '
             . date('g:i A', strtotime($this->closing_time));
    }

    /** Is the destination open at this moment, by its posted hours? */
    public function isOpenNow(): bool
    {
        if (! $this->opening_time || ! $this->closing_time) {
            return false;
        }

        $now   = now()->format('H:i:s');
        $open  = substr($this->opening_time, 0, 8);
        $close = substr($this->closing_time, 0, 8);

        // Handles a closing time past midnight.
        return $close < $open
            ? ($now >= $open || $now <= $close)
            : ($now >= $open && $now <= $close);
    }

    public function color(): string
    {
        return self::CATEGORY_COLORS[$this->category] ?? '#8E3B2F';
    }

    /**
     * Local image path. Falls back to a generated SVG when the church has no
     * uploaded photo, so nothing ever points at a remote URL.
     */
    public function imagePath(): string
    {
        $url = $this->image_url;

        if ($url && ! str_starts_with($url, 'http')) {
            return asset($url);
        }

        $slug = \Illuminate\Support\Str::slug($this->name);

        // PNG first: SVG is for icons, photographs belong in a raster format.
        foreach (['png', 'jpg', 'webp', 'svg'] as $ext) {
            if (file_exists(public_path("images/churches/{$slug}.{$ext}"))) {
                return asset("images/churches/{$slug}.{$ext}");
            }
        }

        return asset('images/churches/placeholder.svg');
    }

    /** Has the signed-in devotee saved this destination? */
    public function isFavorited(): bool
    {
        static $ids = null;

        if (! auth()->check()) {
            return false;
        }

        if ($ids === null) {
            $ids = Favorite::where('user_id', auth()->id())
                ->where('is_active', true)
                ->pluck('church_id')
                ->flip()
                ->all();
        }

        return isset($ids[$this->id]);
    }
}
