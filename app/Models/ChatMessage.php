<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ERD entity: ChatMessages */
class ChatMessage extends Model
{
    protected $table = 'chat_messages';

    public $timestamps = false;

    protected $fillable = ['session_id', 'sender_type', 'message', 'mood', 'plan_url', 'created_at'];

    /** The planner link, only when it is a link on this site (never another host). */
    public function planLink(): ?string
    {
        $url = (string) $this->plan_url;

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : null;
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'session_id');
    }

    public function isFromUser(): bool
    {
        return $this->sender_type === 'user';
    }
}
