<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SizeGuide extends Model
{
    protected $fillable = ['category_id', 'title', 'instructions', 'notes', 'rows', 'is_published'];

    protected function casts(): array
    {
        return ['rows' => 'array', 'is_published' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
