<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who hears about a church: the "Notify me" button on its page.
 *
 * The rule, in one place:
 *   - pressed "Notify me"             -> notified
 *   - saved (favourited) the church   -> notified, unless they turned it off
 *   - pressed it off                  -> not notified, even if saved
 *
 * Saved churches notify by default because that is how GIYA behaved before
 * the button existed; nobody who saved a church silently stops hearing about
 * it.
 */
class ChurchFollow extends Model
{
    protected $table = 'church_follows';

    public $timestamps = false;

    protected $fillable = ['user_id', 'church_id', 'notify', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'notify'     => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    /** Is this devotee being notified about this church? */
    public static function isNotified(int $userId, int $churchId): bool
    {
        $choice = static::where('user_id', $userId)->where('church_id', $churchId)->value('notify');

        if ($choice !== null) {
            return (bool) $choice;
        }

        return Favorite::where('user_id', $userId)->where('church_id', $churchId)
            ->where('is_active', true)->exists();
    }

    /** Flip the button; returns the new state. */
    public static function toggle(int $userId, int $churchId): bool
    {
        $now = static::isNotified($userId, $churchId) ? false : true;

        static::updateOrCreate(
            ['user_id' => $userId, 'church_id' => $churchId],
            ['notify' => $now, 'updated_at' => now(), 'created_at' => now()]
        );

        return $now;
    }

    /**
     * Everyone notified about one church.
     *
     * @return array<int>
     */
    public static function followersOf(int $churchId): array
    {
        $chose = static::where('church_id', $churchId)->pluck('notify', 'user_id');

        $saved = Favorite::where('church_id', $churchId)->where('is_active', true)
            ->pluck('user_id')
            ->reject(fn ($id) => $chose->has($id));   // their own choice wins

        return $chose->filter()->keys()
            ->merge($saved)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Every church one devotee is notified about.
     *
     * @return array<int>
     */
    public static function churchIdsFor(int $userId): array
    {
        $chose = static::where('user_id', $userId)->pluck('notify', 'church_id');

        $saved = Favorite::where('user_id', $userId)->where('is_active', true)
            ->pluck('church_id')
            ->reject(fn ($id) => $chose->has($id));

        return $chose->filter()->keys()
            ->merge($saved)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
