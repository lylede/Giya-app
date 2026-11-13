<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A message from the team to every pilgrim.
 *
 * It is shown two ways: as a banner across the top of the site until it ends
 * or an admin stops it, and (if the admin chose so) once in each pilgrim's
 * notification bell.
 */
class Announcement extends Model
{
    protected $fillable = ['title', 'message', 'created_by', 'is_active', 'show_until', 'sent_to'];

    private const MEMO = 'giya.announcement';

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_until' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Still on the banner: not stopped, and not past its end date. */
    public function scopeShowing(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('show_until')->orWhere('show_until', '>', now()));
    }

    public function isShowing(): bool
    {
        return $this->is_active && ($this->show_until === null || $this->show_until->isFuture());
    }

    /**
     * The one announcement on the banner right now: the newest still showing.
     * Null when there is none, or when the table is not there yet.
     */
    public static function current(): ?self
    {
        $request = request();

        if ($request->attributes->has(self::MEMO)) {
            return $request->attributes->get(self::MEMO);
        }

        try {
            $current = static::query()->showing()->latest('id')->first();
        } catch (\Throwable) {
            $current = null;
        }

        $request->attributes->set(self::MEMO, $current);

        return $current;
    }
}
