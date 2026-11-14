<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\InventoryReservationService;
use App\Services\PaymentService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PaymentInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    private function order(): Order
    {
        $product = Product::create(['name' => 'Payment test', 'slug' => 'payment-'.Str::random(8), 'is_active' => true]);
        $variant = $product->variants()->create(['sku' => 'PAY-'.Str::random(8), 'size' => '4Y', 'color_name' => 'Blue', 'price_toman' => 2500, 'stock_quantity' => 3, 'is_active' => true]);
        $order = Order::create(['number' => (string) Str::ulid(), 'checkout_key' => (string) Str::uuid(), 'guest_access_token' => hash('sha512', Str::random(64)), 'recipient_name' => 'پرداخت', 'phone' => '09120000000', 'address' => 'Tehran', 'postal_code' => '123', 'currency' => 'IRR', 'subtotal_toman' => 2500, 'status' => Order::PENDING_PAYMENT, 'payment_status' => Order::PAYMENT_PENDING, 'fulfillment_status' => Order::FULFILLMENT_AWAITING, 'shipment_status' => Order::SHIPMENT_NOT_SHIPPED]);
        $order->items()->create(['product_variant_id' => $variant->id, 'sku' => $variant->sku, 'product_name' => $product->name, 'size' => $variant->size, 'color_name' => $variant->color_name, 'unit_price_toman' => 2500, 'quantity' => 1, 'line_total_toman' => 2500]);
        app(InventoryReservationService::class)->reserveOrder($order, [['variant' => $variant, 'quantity' => 1]], $order->checkout_key);

        return $order->fresh(['items.variant']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('payment.mock_enabled', true);
        Config::set('payment.gateway', 'mock');
    }

    public function test_mock_success_uses_signed_flow_and_commits_inventory_once(): void
    {
        $order = $this->order();
        $response = $this->withSession(['completed_order_number' => $order->number])->post(route('payments.initiate', $order));
        $response->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('هیچ پرداخت واقعی');
        $action = URL::temporarySignedRoute('payments.mock.action', now()->addMinutes(5), ['payment' => $payment]);
        $this->post($action, ['action' => 'success'])->assertRedirect();
        $this->post($action, ['action' => 'success'])->assertStatus(410);
        $this->assertSame(Payment::SUCCEEDED, $payment->fresh()->status);
        $this->assertSame(Order::PAYMENT_PAID, $order->fresh()->payment_status);
        $this->assertSame(2, $order->items->first()->variant->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_movements', ['movement_type' => 'sale_fulfillment', 'quantity_delta' => -1]);
    }

    public function test_mock_is_fail_closed_when_disabled_or_in_production(): void
    {
        $order = $this->order();
        Config::set('payment.mock_enabled', false);
        $this->withSession(['completed_order_number' => $order->number])->post(route('payments.initiate', $order))->assertNotFound();
        Config::set('payment.mock_enabled', true);
        $this->app->detectEnvironment(fn () => 'production');
        $productionResponse = $this->withSession(['completed_order_number' => $order->number])->post(route('payments.initiate', $order));
        $this->assertContains($productionResponse->status(), [404, 419]);
        $this->app->detectEnvironment(fn () => 'testing');
    }

    public function test_signed_mock_urls_expire_and_order_ownership_is_enforced(): void
    {
        $order = $this->order();
        $other = $this->order();
        $this->post(route('payments.initiate', $other))->assertNotFound();
        $this->withSession(['completed_order_number' => $order->number])->post(route('payments.initiate', $order));
        $payment = Payment::firstOrFail();
        $expired = URL::temporarySignedRoute('payments.mock.show', now()->subMinute(), ['payment' => $payment]);
        $this->get($expired)->assertForbidden();
    }

    public function test_failed_attempt_is_preserved_and_retry_creates_new_attempt(): void
    {
        $order = $this->order();
        $session = ['completed_order_number' => $order->number];
        $this->withSession($session)->post(route('payments.initiate', $order));
        $first = Payment::firstOrFail();
        $this->post(URL::temporarySignedRoute('payments.mock.action', now()->addMinutes(5), ['payment' => $first]), ['action' => 'failed']);
        $this->assertSame(Payment::FAILED, $first->fresh()->status);
        $this->withSession($session)->post(route('payments.initiate', $order));
        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseHas('payments', ['status' => Payment::FAILED]);
    }

    public function test_browser_success_parameter_cannot_mark_order_paid(): void
    {
        $order = $this->order();
        $this->get(route('checkout.complete', $order).'?status=success')->assertNotFound();
        $this->assertSame(Order::PAYMENT_PENDING, $order->fresh()->payment_status);
    }

    public function test_payment_idempotency_key_cannot_be_reused_for_another_order(): void
    {
        $first = $this->order();
        $second = $this->order();
        $service = app(PaymentService::class);

        $service->initiate($first, sessionOrderNumber: $first->number, idempotencyKey: 'shared-payment-key');

        $this->expectException(ValidationException::class);
        $service->initiate($second, sessionOrderNumber: $second->number, idempotencyKey: 'shared-payment-key');
    }

    public function test_expiration_and_reconciliation_commands_are_rerunnable(): void
    {
        $order = $this->order();
        $this->withSession(['completed_order_number' => $order->number])->post(route('payments.initiate', $order));
        Payment::query()->update(['expires_at' => now()->subMinute()]);
        $this->artisan('payments:expire')->assertSuccessful();
        $this->artisan('payments:expire')->assertSuccessful();
        $this->artisan('payments:reconcile', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(Payment::EXPIRED, Payment::firstOrFail()->status);
    }

    public function test_mock_partial_refund_is_idempotent_limited_and_does_not_restock(): void
    {
        $order = $this->order();
        $this->withSession(['completed_order_number' => $order->number])->post(route('payments.initiate', $order));
        $payment = Payment::firstOrFail();
        $this->post(URL::temporarySignedRoute('payments.mock.action', now()->addMinutes(5), ['payment' => $payment]), ['action' => 'success']);
        $refunds = app(RefundService::class);
        $refund = $refunds->request($order->fresh(), 1000, null, 'تست');
        $refunds->completeMock($refund);
        $refunds->completeMock($refund);
        $this->assertSame(Payment::PARTIALLY_REFUNDED, $payment->fresh()->status);
        $this->assertSame(2, $order->items->first()->variant->fresh()->stock_quantity);
        $this->expectException(ValidationException::class);
        $refunds->request($order->fresh(), 2000, null, 'بیش از حد');
    }
}
