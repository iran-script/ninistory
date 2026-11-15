<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $start = now()->subDays(6)->startOfDay();
        $paidOrders = Order::query()
            ->where('payment_status', Order::PAYMENT_PAID)
            ->where('created_at', '>=', $start)
            ->get(['created_at', 'subtotal_toman', 'shipping_toman', 'discount_toman']);

        $sales = collect(range(6, 0))->map(function (int $daysAgo) use ($paidOrders): array {
            $date = now()->subDays($daysAgo)->startOfDay();
            $orders = $paidOrders->filter(fn (Order $order): bool => $order->created_at->isSameDay($date));

            return [
                'label' => $date->format('m/d'),
                'orders' => $orders->count(),
                'revenue' => (int) $orders->sum(fn (Order $order): int => (int) $order->subtotal_toman + (int) $order->shipping_toman - (int) $order->discount_toman),
            ];
        });
        $maxRevenue = max(1, (int) $sales->max('revenue'));
        $salesPoints = $sales->values()->map(function (array $point, int $index) use ($sales, $maxRevenue): string {
            $x = $sales->count() > 1 ? $index * (600 / ($sales->count() - 1)) : 300;
            $y = 160 - ((int) $point['revenue'] / $maxRevenue * 132);

            return number_format($x, 1, '.', '').','.number_format($y, 1, '.', '');
        })->implode(' ');

        $variants = ProductVariant::query()->where('is_active', true)->get(['stock_quantity', 'reserved_quantity']);
        $inStock = $variants->filter(fn (ProductVariant $variant): bool => $variant->available_quantity > 0)->count();
        $outOfStock = $variants->count() - $inStock;

        return view('admin.dashboard', [
            'products' => Product::count(),
            'published' => Product::where('is_active', true)->count(),
            'drafts' => Product::where('is_active', false)->count(),
            'categories' => Category::count(),
            'lowStock' => $variants->filter(fn (ProductVariant $variant): bool => $variant->is_low_stock)->count(),
            'outOfStock' => $outOfStock,
            'inStock' => $inStock,
            'sales' => $sales,
            'salesPoints' => $salesPoints,
            'salesTotal' => (int) $sales->sum('revenue'),
            'todayOrders' => Order::whereDate('created_at', today())->count(),
            'recentOrders' => Order::query()->latest()->limit(5)->get(),
            'bestSellers' => OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.payment_status', Order::PAYMENT_PAID)
                ->selectRaw('product_name, SUM(quantity) as units_sold, SUM(line_total_toman) as revenue')
                ->groupBy('product_name')->orderByDesc('units_sold')->limit(5)->get(),
            'changes' => InventoryMovement::with('variant.product')->latest()->limit(6)->get(),
        ]);
    }
}
