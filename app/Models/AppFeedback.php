<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Feedback about the GIYA experience (not about a church). */
class AppFeedback extends Model
{
    protected $table = 'app_feedback';

    public $timestamps = false;

    public const TOPICS = ['map', 'ai', 'planning', 'mass', 'account', 'other'];

    protected $fillable = ['user_id', 'rating', 'topic', 'comment', 'created_at'];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topicLabel(): string
    {
        return __('giya.app_feedback.topic_'.$this->topic);
    }
}
