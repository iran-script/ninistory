<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id(); $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity_delta'); $table->string('reason', 40); $table->string('reference')->nullable(); $table->timestamps(); $table->index(['product_variant_id', 'created_at']);
        });
        Schema::create('carts', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('session_id')->nullable()->index(); $table->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('cart_id')->constrained()->cascadeOnDelete(); $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete(); $table->unsignedSmallInteger('quantity'); $table->timestamps(); $table->unique(['cart_id', 'product_variant_id']);
        });
        Schema::create('addresses', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('recipient_name',160); $table->string('phone',20); $table->string('province'); $table->string('city'); $table->text('address'); $table->string('postal_code',20); $table->boolean('is_default')->default(false); $table->timestamps();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id(); $table->string('number')->unique(); $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); $table->string('recipient_name',160); $table->string('phone',20); $table->text('address'); $table->string('postal_code',20);
            $table->string('currency',3)->default('IRR'); $table->unsignedBigInteger('subtotal_toman'); $table->unsignedBigInteger('discount_toman')->default(0); $table->unsignedBigInteger('shipping_toman')->default(0); $table->string('status',32)->default('pending_payment'); $table->timestamps(); $table->index(['user_id','created_at']);
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete(); $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete(); $table->string('sku'); $table->string('product_name'); $table->string('size'); $table->string('color_name'); $table->unsignedBigInteger('unit_price_toman'); $table->unsignedSmallInteger('quantity'); $table->unsignedBigInteger('line_total_toman'); $table->timestamps();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete(); $table->string('provider',40); $table->string('provider_reference')->nullable()->unique(); $table->unsignedBigInteger('amount_rials'); $table->string('status',24)->default('pending'); $table->timestamps(); $table->index(['order_id','status']);
        });
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('product_id')->constrained()->cascadeOnDelete(); $table->timestamps(); $table->unique(['user_id','product_id']);
        });
    }
    public function down(): void
    {
        foreach (['wishlists','payments','order_items','orders','addresses','cart_items','carts','inventory_movements'] as $table) Schema::dropIfExists($table);
    }
};
