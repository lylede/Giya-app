<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One line per thing an admin did in the admin panel.
 *
 * Written by the LogAdminActivity middleware, never by hand, and there is no
 * screen to edit or delete an entry: a log an admin can tidy is not a log.
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'admin_name', 'action', 'subject', 'details', 'route', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Never stops the action it is recording: if the table is not there yet,
     * or the write fails, the admin's work still goes through.
     */
    public static function record(User $admin, string $action, ?string $subject = null, ?string $details = null, ?string $route = null): void
    {
        try {
            static::create([
                'user_id'    => $admin->id,
                'admin_name' => Str::limit($admin->name, 97),
                'action'     => Str::limit($action, 77),
                'subject'    => $subject !== null ? Str::limit($subject, 197) : null,
                'details'    => $details !== null ? Str::limit($details, 297) : null,
                'route'      => $route !== null ? Str::limit($route, 77) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // A note in the server log, not an alert: before "php artisan migrate"
            // this happens on every admin action and would flood the team's alerts.
            logger()->warning('Activity log entry not saved: '.$e->getMessage());
        }
    }
}
