<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description'
    ];

    protected static function booted(): void
    {
        static::saved(function ($setting) {
            Cache::forget("system_setting_{$setting->key}");
            if ($setting->key === 'system_setting_cache_ttl_seconds') {
                Cache::forget('system_setting_cache_ttl_val');
            }
        });
        static::deleted(function ($setting) {
            Cache::forget("system_setting_{$setting->key}");
            if ($setting->key === 'system_setting_cache_ttl_seconds') {
                Cache::forget('system_setting_cache_ttl_val');
            }
        });
    }

    /**
     * Get a system setting value by its key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        static $ttl = null;
        if ($ttl === null) {
            $ttl = Cache::remember("system_setting_cache_ttl_val", now()->addSeconds(10), function () {
                $setting = static::where('key', 'system_setting_cache_ttl_seconds')->first();
                return $setting ? (int) $setting->value : 30;
            });
        }

        return Cache::remember("system_setting_{$key}", now()->addSeconds($ttl), function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }
}
