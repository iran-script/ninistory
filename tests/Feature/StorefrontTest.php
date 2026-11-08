<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_persian_homepage_and_listing_render_utf8(): void
    {
        $this->seed();
        $this->get('/')->assertOk()->assertSee('نینی‌استوری');
        $this->get('/products?q=سرهمی')->assertOk()->assertSee('سرهمی');
    }

    public function test_cart_rejects_quantity_above_stock_and_accepts_valid_variant(): void
    {
        $this->seed();
        $variant = Product::firstOrFail()->variants()->firstOrFail();
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 9])->assertRedirect();
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 9])->assertSessionHasErrors('variant_id');
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect(route('cart'));
        $this->get(route('cart'))->assertOk()->assertSee('1,780,000');
    }

    public function test_guest_checkout_creates_pending_order_and_decrements_stock(): void
    {
        $this->seed();
        $variant = Product::firstOrFail()->variants()->firstOrFail();
        $this->post(route('cart.add'), ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post(route('checkout.store'), ['recipient_name'=>'آزمایش', 'phone'=>'09120000000', 'address'=>'تهران', 'postal_code'=>'1234567890'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['subtotal_toman'=>1780000, 'status'=>'pending_payment', 'currency'=>'IRR']);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(6, $variant->fresh()->stock_quantity);
    }
}
