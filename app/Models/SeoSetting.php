<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoSetting extends Model
{
    protected $fillable = ['site_name', 'tagline', 'default_title', 'default_description', 'organization_name', 'organization_logo_path', 'search_console_verification', 'analytics_measurement_id', 'indexing_enabled'];

    protected function casts(): array
    {
        return ['indexing_enabled' => 'boolean'];
    }
}
