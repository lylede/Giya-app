<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ERD entity: ChurchImages */
class ChurchImage extends Model
{
    protected $table = 'church_images';

    public $timestamps = false;

    protected $fillable = [
        'church_id', 'image_url', 'caption', 'credit', 'credit_url', 'is_primary', 'uploaded_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_primary'  => 'boolean',
            'uploaded_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    /** A free-licensed photo from Wikimedia Commons (shown with its credit). */
    public static function isCommons(?string $url): bool
    {
        return is_string($url) && (str_starts_with($url, 'https://upload.wikimedia.org/wikipedia/commons/')
            || str_starts_with($url, 'https://thumb.wikimedia.org/wikipedia/commons/'));
    }

    public function church(): BelongsTo
    {
        return $this->belongsTo(Church::class);
    }

    public function path(): string
    {
        return str_starts_with($this->image_url, 'http')
            ? $this->image_url
            : asset($this->image_url);
    }
}
