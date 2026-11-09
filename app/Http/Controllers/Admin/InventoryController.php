<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        $variants = ProductVariant::with(['product.category', 'inventoryMovements' => fn ($q) => $q->latest()->limit(1)])
            ->when($request->filled('sku'), fn ($q) => $q->where('sku', 'like', '%'.$request->input('sku').'%'))
            ->when($request->filled('product'), fn ($q) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', '%'.$request->input('product').'%')))
            ->when($request->filled('category_id'), fn ($q) => $q->whereHas('product', fn ($p) => $p->where('category_id', $request->integer('category_id'))))
            ->when($request->boolean('out_of_stock'), fn ($q) => $q->whereRaw('stock_quantity <= reserved_quantity'))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereRaw('stock_quantity > reserved_quantity AND (stock_quantity - reserved_quantity) <= ?', [config('inventory.low_stock_threshold', 5)]))
            ->orderBy('sku')->paginate(25)->withQueryString();
        return view('admin.inventory.index', ['variants' => $variants, 'categories' => Category::orderBy('name')->get()]);
    }

    public function receive(Request $request, ProductVariant $variant)
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:1000000'], 'reason' => ['required', 'string', 'min:3', 'max:180']]);
        app(InventoryService::class)->receive($variant, $data['quantity'], $data['reason'], $request->user());
        return back()->with('status', 'دریافت موجودی ثبت شد.');
    }

    public function movements(ProductVariant $variant)
    {
        return view('admin.inventory.movements', ['variant' => $variant->load('product'), 'movements' => $variant->inventoryMovements()->latest()->paginate(30)]);
    }

    public function reservations()
    {
        return view('admin.inventory.reservations', ['reservations' => StockReservation::with(['variant.product', 'order'])->latest()->paginate(30)]);
    }
}
