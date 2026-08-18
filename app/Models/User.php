<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * ERD entity: Devotees
 *
 * The class keeps the name User so every existing controller and view import
 * still resolves; only the underlying table changed.
 *
 * The profile counters (total_pilgrimages, total_churches_visited,
 * total_km_walked, is_premium, member_since) are NOT columns any more — the
 * ERD has no place for them. They are derived from the related tables and
 * exposed as accessors, so `$user->total_churches_visited` still works in
 * Blade exactly as before.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'devotees';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'status',
        'last_seen_at',
        'avatar_url',
        'created_at',
        'updated_at',
    ];

    protected $hidden = ['password_hash', 'remember_token'];

    /** Derived values Blade can read straight off the model. */
    protected $appends = [
        'is_premium',
        'total_pilgrimages',
        'total_churches_visited',
        'total_km_walked',
        'member_since',
    ];

    protected function casts(): array
    {
        return [
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** The schema stores the hash in password_hash, not password. */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    public const STATUSES = ['Active', 'Inactive', 'Suspended'];

    /** A suspended account cannot sign in. */
    public function isSuspended(): bool
    {
        return $this->status === 'Suspended';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function initials(): string
    {
        return strtoupper(mb_substr($this->name, 0, 1));
    }

    public function firstName(): string
    {
        return explode(' ', trim($this->name))[0];
    }
    public function preferences(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DevoteePreference::class, 'user_id');
    }

    /* ------------------------------ Relationships --------------------------- */
   

    public function itineraries(): HasMany
    {
        return $this->hasMany(Itinerary::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(VisitHistory::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    /* --------------------- Derived profile stats (was columns) -------------- */

    /** A paid, unexpired transaction makes the devotee premium. */
    public function getIsPremiumAttribute(): bool
    {
        return $this->transactions()
            ->where('status', 'Paid')
            ->whereHas('subscriptionPlan', fn ($q) => $q->where('is_active', true))
            ->get()
            ->contains(fn (Transaction $t) => $t->isStillValid());
    }

    public function getTotalPilgrimagesAttribute(): int
    {
        return $this->itineraries()->where('status', 'Completed')->count();
    }

    public function getTotalChurchesVisitedAttribute(): int
    {
        return $this->visits()->distinct('church_id')->count('church_id');
    }
      public function preferencesOrDefault(): DevoteePreference
    {
        return $this->preferences()->firstOrCreate(
            ['user_id' => $this->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
    }
    /** Absolute URL for the avatar, or null when the devotee has none. */
    public function avatarPath(): ?string
    {
        if (! $this->avatar_url) {
            return null;
        }

        return str_starts_with($this->avatar_url, 'http')
            ? $this->avatar_url
            : asset($this->avatar_url);
    }
    /**
     * Straight-line distance between consecutive visited stops, summed across
     * the devotee's itineraries. Replaces the old stored counter.
     */
    public function getTotalKmWalkedAttribute(): float
    {
        $total = 0.0;

        $this->itineraries()
            ->with(['stops' => fn ($q) => $q->where('is_visited', true)
                                            ->orderBy('stop_order')
                                            ->with('church')])
            ->get()
            ->each(function (Itinerary $itinerary) use (&$total) {
                $points = $itinerary->stops
                    ->pluck('church')
                    ->filter(fn ($c) => $c && $c->latitude && $c->longitude)
                    ->values();

                for ($i = 1; $i < $points->count(); $i++) {
                    $total += self::haversine(
                        (float) $points[$i - 1]->latitude, (float) $points[$i - 1]->longitude,
                        (float) $points[$i]->latitude,     (float) $points[$i]->longitude
                    );
                }
            });

        return round($total, 2);
    }

    public function getMemberSinceAttribute()
    {
        return $this->created_at;
    }

    /** Great-circle distance in kilometres. */
    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371;
        $dLat  = deg2rad($lat2 - $lat1);
        $dLng  = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
