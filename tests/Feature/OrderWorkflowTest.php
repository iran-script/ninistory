<?php

namespace Tests\Feature;

use App\Models\{Order, Product, User};
use App\Services\{InventoryReservationService, OrderInventoryService, OrderWorkflowService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function order(int $quantity = 1): Order
    {
        $product = Product::create(['name' => 'لباس تست', 'slug' => 'workflow-'.Str::random(8), 'is_active' => true]);
        $variant = $product->variants()->create(['sku' => 'WF-'.Str::random(8), 'size' => '4Y', 'color_name' => 'Pink', 'price_toman' => 1000, 'stock_quantity' => 5, 'is_active' => true]);
        $order = Order::create(['number' => (string) Str::ulid(), 'checkout_key' => (string) Str::uuid(), 'guest_access_token' => str_repeat('a', 128), 'recipient_name' => 'Test', 'phone' => '09120000000', 'address' => 'Tehran', 'postal_code' => '123', 'currency' => 'IRR', 'subtotal_toman' => 1000 * $quantity, 'status' => Order::PENDING_PAYMENT, 'payment_status' => Order::PAYMENT_PENDING, 'fulfillment_status' => Order::FULFILLMENT_AWAITING, 'shipment_status' => Order::SHIPMENT_NOT_SHIPPED]);
        $order->items()->create(['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'product_name' => $product->name, 'size' => $variant->size, 'color_name' => $variant->color_name, 'unit_price_toman' => 1000, 'quantity' => $quantity, 'line_total_toman' => 1000 * $quantity]);
        app(InventoryReservationService::class)->reserveOrder($order, [['variant' => $variant, 'quantity' => $quantity]], $order->checkout_key);
        return $order->fresh(['items.variant']);
    }

    public function test_workflow_keeps_payment_fulfillment_and_shipment_independent_and_ship_is_idempotent(): void
    {
        $order = $this->order(); $admin = User::factory()->create(['is_admin' => true]);
        app(OrderInventoryService::class)->confirmPayment($order);
        $workflow = app(OrderWorkflowService::class);
        $workflow->startProcessing($order, $admin); $workflow->pack($order, $admin);
        $workflow->ship($order, ['carrier' => 'پست', 'shipping_method' => 'عادی', 'tracking_code' => 'TRK-1'], $admin);
        $workflow->ship($order, ['carrier' => 'متفاوت', 'shipping_method' => 'متفاوت', 'tracking_code' => 'TRK-2'], $admin);
        $this->assertDatabaseCount('shipments', 1);
        $this->assertDatabaseHas('orders', ['payment_status' => Order::PAYMENT_PAID, 'fulfillment_status' => Order::FULFILLMENT_PACKED, 'shipment_status' => Order::SHIPMENT_SHIPPED]);
    }

    public function test_paid_cancellation_sets_refund_obligation_without_restocking(): void
    {
        $order = $this->order(); $admin = User::factory()->create(['is_admin' => true]);
        app(OrderInventoryService::class)->confirmPayment($order);
        $variant = $order->items->first()->variant;
        app(OrderWorkflowService::class)->cancel($order, $admin, 'درخواست مشتری');
        app(OrderWorkflowService::class)->cancel($order, $admin, 'تکراری');
        $this->assertSame(4, $variant->fresh()->stock_quantity);
        $this->assertSame(Order::REFUND_PENDING, $order->fresh()->refund_status);
        $this->assertSame(1, $order->fresh()->events()->where('event_type', 'cancelled')->count());
    }

    public function test_customer_order_detail_requires_owner_and_guest_token(): void
    {
        $order = $this->order(); $owner = User::factory()->create(); $other = User::factory()->create();
        $order->update(['user_id' => $owner->id]);
        $this->actingAs($other)->get(route('orders.show', $order))->assertNotFound();
        $this->get(route('orders.guest', str_repeat('a', 128)))->assertOk();
        $this->get(route('orders.guest', str_repeat('b', 128)))->assertNotFound();
    }

    public function test_unpaid_order_cancellation_releases_reservation_once(): void
    {
        $order = $this->order(); $admin = User::factory()->create(['is_admin' => true]); $variant = $order->items->first()->variant;
        app(OrderWorkflowService::class)->cancel($order, $admin, 'عدم پرداخت');
        app(OrderWorkflowService::class)->cancel($order, $admin, 'تکراری');
        $this->assertSame(0, $variant->fresh()->reserved_quantity);
        $this->assertSame(0, $order->fresh()->reservations()->where('status', 'active')->count());
    }
}
