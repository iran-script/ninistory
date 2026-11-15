<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SeoSetting extends Model
{
    public const CACHE_KEY = 'seo:settings:v2';

    protected $fillable = ['site_name', 'tagline', 'default_title', 'default_description', 'organization_name', 'organization_logo_path', 'search_console_verification', 'analytics_measurement_id', 'indexing_enabled'];

    protected function casts(): array
    {
        return ['indexing_enabled' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(self::CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(self::CACHE_KEY));
    }
}
