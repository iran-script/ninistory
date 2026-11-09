<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductVariant;

class DashboardController extends Controller
{
    public function __invoke()
    {
        return view('admin.dashboard', [
            'products' => Product::count(), 'published' => Product::where('is_active', true)->count(),
            'drafts' => Product::where('is_active', false)->count(), 'categories' => Category::count(),
            'lowStock' => ProductVariant::where('is_active', true)->whereRaw('stock_quantity > reserved_quantity AND (stock_quantity - reserved_quantity) BETWEEN 1 AND ?', [config('inventory.low_stock_threshold', 5)])->count(),
            'outOfStock' => ProductVariant::where('is_active', true)->whereRaw('stock_quantity <= reserved_quantity')->count(),
            'changes' => InventoryMovement::with('variant.product')->latest()->limit(8)->get(),
        ]);
    }
}
