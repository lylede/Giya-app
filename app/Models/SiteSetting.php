<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per setting: a name and its value.
 *
 * Read once per request. If the table is not there yet (the code was deployed
 * before "php artisan migrate" was run), every setting reads as its default
 * and the site carries on.
 */
class SiteSetting extends Model
{
    protected $table = 'site_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['key', 'value', 'updated_at'];

    private const MEMO = 'giya.site_settings';

    /** @return array<string, ?string> */
    public static function values(): array
    {
        $request = request();

        if ($request->attributes->has(self::MEMO)) {
            return $request->attributes->get(self::MEMO);
        }

        try {
            $values = static::query()->pluck('value', 'key')->all();
        } catch (\Throwable) {
            $values = [];
        }

        $request->attributes->set(self::MEMO, $values);

        return $values;
    }

    public static function read(string $key, ?string $default = null): ?string
    {
        return static::values()[$key] ?? $default;
    }

    /** @param  array<string, ?string>  $values */
    public static function write(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value, 'updated_at' => now()]);
        }

        request()->attributes->remove(self::MEMO);
    }
}
