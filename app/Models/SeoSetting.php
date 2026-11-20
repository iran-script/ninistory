<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SeoSetting extends Model
{
    public const CACHE_KEY = 'seo:settings:v2';

    protected $fillable = ['site_name', 'tagline', 'default_title', 'default_description', 'organization_name', 'organization_logo_path', 'search_console_verification', 'analytics_measurement_id', 'indexing_enabled', 'ai_enabled', 'ai_provider', 'ai_api_key', 'ai_model', 'ai_max_output_length', 'ai_timeout_seconds', 'ai_default_tone'];

    protected function casts(): array
    {
        return ['indexing_enabled' => 'boolean', 'ai_enabled' => 'boolean', 'ai_api_key' => 'encrypted'];
    }

    protected static function booted(): void
    {
        static::saved(fn (): bool => Cache::forget(self::CACHE_KEY));
        static::deleted(fn (): bool => Cache::forget(self::CACHE_KEY));
    }
}
