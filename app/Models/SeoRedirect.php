<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoRedirect extends Model
{
    protected $fillable = ['source_path', 'target_url', 'status_code'];

    protected function casts(): array
    {
        return ['status_code' => 'integer'];
    }
}
