<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'width', 'height', 'alt_text', 'caption', 'sort_order', 'is_cover', 'color_name', 'product_variant_id'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'is_cover' => 'boolean', 'sort_order' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
