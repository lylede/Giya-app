<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';

    public $timestamps = false;

    protected $fillable = ['user_id', 'type', 'title', 'message', 'url', 'dedupe_key', 'is_read', 'created_at'];

    protected function casts(): array
    {
        return ['is_read' => 'boolean', 'created_at' => 'datetime'];
    }

    /**
     * Links are kept as a path ("/churches/12"), never with a site address.
     * The same database serves Vercel and Render: a full link saved on one
     * sent the reader to the other. Old full links are trimmed when read.
     */
    protected function url(): Attribute
    {
        $relative = static function (?string $url): ?string {
            if ($url === null || $url === '' || ! preg_match('#^https?://#i', $url)) {
                return $url;
            }

            $parts = parse_url($url);
            $path  = ($parts['path'] ?? '') !== '' ? $parts['path'] : '/';

            return $path
                .(isset($parts['query']) ? '?'.$parts['query'] : '')
                .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
        };

        return Attribute::make(get: $relative, set: $relative);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    /** One line for creating a notification from anywhere in the app. */
    public static function notify(int $userId, string $title, array $options = []): self
    {
        return static::create([
            'user_id'    => $userId,
            'type'       => $options['type'] ?? 'general',
            'title'      => $title,
            'message'    => $options['message'] ?? null,
            'url'        => $options['url'] ?? null,
            'is_read'    => false,
            'created_at' => now(),
        ]);
    }

    /** Bootstrap icon name for the row, chosen by type. */
    public function getIconAttribute(): string
    {
        return [
            'schedule'  => 'calendar-event-fill',
            'feedback'  => 'star-fill',
            'itinerary' => 'signpost-fill',
            'system'    => 'info-circle',
        ][$this->type] ?? 'bell-fill';
    }
}
