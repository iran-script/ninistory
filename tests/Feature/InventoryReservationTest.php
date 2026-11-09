<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\InventoryReservationService;
use App\Services\InventoryService;
use App\Services\OrderInventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InventoryReservationTest extends TestCase
{
    use RefreshDatabase;

    private function variant(int $stock = 5)
    {
        $product = Product::create(['name' => 'Inventory product', 'slug' => 'inventory-product-'.uniqid(), 'is_active' => true]);
        return $product->variants()->create(['sku' => 'INV-'.uniqid(), 'size' => '3Y', 'color_name' => 'Pink', 'price_toman' => 1000, 'stock_quantity' => $stock, 'is_active' => true]);
    }

    private function order(?string $key = null): Order
    {
        return Order::create(['number' => (string) \Illuminate\Support\Str::ulid(), 'checkout_key' => $key ?: (string) \Illuminate\Support\Str::uuid(), 'recipient_name' => 'Test', 'phone' => '09120000000', 'address' => 'Tehran', 'postal_code' => '123', 'currency' => 'IRR', 'subtotal_toman' => 1000, 'status' => Order::PENDING_PAYMENT]);
    }

    public function test_receipt_and_adjustment_preserve_reserved_floor(): void
    {
        $variant = $this->variant(5); $order = $this->order();
        $order->items()->create(['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'product_name' => 'Inventory product', 'size' => '3Y', 'color_name' => 'Pink', 'unit_price_toman' => 1000, 'quantity' => 3, 'line_total_toman' => 3000]);
        app(InventoryReservationService::class)->reserveOrder($order, [['variant' => $variant, 'quantity' => 3]], $order->checkout_key);
        $this->expectException(InsufficientStockException::class);
        app(InventoryService::class)->setPhysicalStock($variant, 2, 'too low');
    }

    public function test_reservation_is_all_or_nothing_and_second_attempt_cannot_oversell(): void
    {
        $a = $this->variant(1); $b = $this->variant(1); $first = $this->order(); $second = $this->order();
        app(InventoryReservationService::class)->reserveOrder($first, [['variant' => $a, 'quantity' => 1], ['variant' => $b, 'quantity' => 1]], $first->checkout_key);
        try { app(InventoryReservationService::class)->reserveOrder($second, [['variant' => $a, 'quantity' => 1], ['variant' => $b, 'quantity' => 1]], $second->checkout_key); $this->fail('Expected insufficient stock.'); } catch (InsufficientStockException) {}
        $this->assertSame(1, $a->fresh()->reserved_quantity); $this->assertSame(1, $b->fresh()->reserved_quantity); $this->assertSame(0, $second->reservations()->count());
    }

    public function test_reservation_expiration_releases_once_and_does_not_release_committed(): void
    {
        $variant = $this->variant(3); $order = $this->order();
        app(InventoryReservationService::class)->reserveOrder($order, [['variant' => $variant, 'quantity' => 2]], $order->checkout_key);
        StockReservation::query()->update(['expires_at' => Carbon::now()->subMinute()]);
        $this->artisan('inventory:expire-reservations')->assertSuccessful();
        $this->assertSame(0, $variant->fresh()->reserved_quantity); $this->assertSame(Order::PAYMENT_EXPIRED, $order->fresh()->status);
        $this->artisan('inventory:expire-reservations')->assertSuccessful();
        $this->assertSame(0, $variant->fresh()->reserved_quantity);
    }

    public function test_cancel_releases_and_payment_confirmation_commits_idempotently(): void
    {
        $variant = $this->variant(3); $order = $this->order();
        $order->items()->create(['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'product_name' => 'Inventory product', 'size' => '3Y', 'color_name' => 'Pink', 'unit_price_toman' => 1000, 'quantity' => 2, 'line_total_toman' => 2000]);
        app(InventoryReservationService::class)->reserveOrder($order, [['variant' => $variant, 'quantity' => 2]], $order->checkout_key);
        app(OrderInventoryService::class)->confirmPayment($order);
        app(OrderInventoryService::class)->confirmPayment($order);
        $this->assertSame(1, $variant->fresh()->stock_quantity); $this->assertSame(0, $variant->fresh()->reserved_quantity); $this->assertSame(Order::PAID, $order->fresh()->status);
    }

    public function test_duplicate_checkout_key_is_idempotent(): void
    {
        $this->seed(); $variant = Product::firstOrFail()->variants()->firstOrFail();
        $payload = ['recipient_name' => 'Test', 'phone' => '09120000000', 'address' => 'Tehran', 'postal_code' => '123', 'checkout_key' => 'same-checkout-key'];
        $this->withSession(['cart' => [$variant->id => 1]])->post(route('checkout.store'), $payload)->assertRedirect();
        $this->withSession(['cart' => [$variant->id => 1]])->post(route('checkout.store'), $payload)->assertRedirect();
        $this->assertDatabaseCount('orders', 1); $this->assertDatabaseCount('stock_reservations', 1);
    }

    public function test_stock_receipt_increases_physical_stock_and_creates_a_movement(): void
    {
        $variant = $this->variant(2); app(InventoryService::class)->receive($variant, 3, 'رسید انبار');
        $this->assertSame(5, $variant->fresh()->stock_quantity); $this->assertDatabaseHas('inventory_movements', ['movement_type' => 'receipt', 'quantity_delta' => 3]);
    }

    public function test_admin_stock_change_requires_admin_authentication(): void
    {
        $variant = $this->variant(2); $response = $this->patch(route('admin.variants.stock', $variant), ['quantity' => 4, 'reason' => 'test']);
        $response->assertRedirect(route('admin.login')); $this->assertSame(2, $variant->fresh()->stock_quantity);
    }

    public function test_inactive_variant_cannot_be_purchased(): void
    {
        $variant = $this->variant(2); $variant->update(['is_active' => false]);
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 1])->assertNotFound();
    }

    public function test_cancellation_releases_reservation_once(): void
    {
        $variant = $this->variant(2); $order = $this->order(); app(InventoryReservationService::class)->reserveOrder($order, [['variant' => $variant, 'quantity' => 1]], $order->checkout_key);
        app(OrderInventoryService::class)->cancel($order); app(OrderInventoryService::class)->cancel($order);
        $this->assertSame(0, $variant->fresh()->reserved_quantity); $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    }

    public function test_authorized_return_restocks_once_after_payment(): void
    {
        $variant = $this->variant(3); $order = $this->order(); $order->items()->create(['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'product_name' => 'Inventory product', 'size' => '3Y', 'color_name' => 'Pink', 'unit_price_toman' => 1000, 'quantity' => 1, 'line_total_toman' => 1000]);
        app(InventoryReservationService::class)->reserveOrder($order, [['variant' => $variant, 'quantity' => 1]], $order->checkout_key); app(OrderInventoryService::class)->confirmPayment($order); app(OrderInventoryService::class)->restockReturn($order, 'مرجوعی تأییدشده', User::factory()->create()); app(OrderInventoryService::class)->restockReturn($order, 'تکرار مرجوعی');
        $this->assertSame(3, $variant->fresh()->stock_quantity); $this->assertSame(Order::RETURNED, $order->fresh()->status);
    }
}
