<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 24)->default('pending')->after('status');
            $table->string('fulfillment_status', 32)->default('awaiting_payment')->after('payment_status');
            $table->string('shipment_status', 32)->default('not_shipped')->after('fulfillment_status');
            $table->string('guest_access_token', 96)->nullable()->unique()->after('checkout_key');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete()->after('user_id');
            $table->text('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->index(['payment_status', 'fulfillment_status', 'shipment_status']);
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 48);
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->text('reason')->nullable();
            $table->string('idempotency_key', 140)->nullable()->unique();
            $table->timestamps();
            $table->index(['order_id', 'created_at']);
        });

        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier', 80)->nullable();
            $table->string('shipping_method', 80)->nullable();
            $table->string('tracking_code', 120)->nullable();
            $table->string('status', 32)->default('not_shipped');
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'tracking_code']);
            $table->index(['status', 'tracking_code']);
        });

        Schema::create('order_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('requested');
            $table->text('reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_id')->constrained('returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('quantity');
            $table->string('disposition', 20)->default('pending');
            $table->text('inspection_note')->nullable();
            $table->timestamps();
            $table->unique(['return_id', 'order_item_id']);
        });

        DB::table('orders')->where('status', 'pending_payment')->update(['payment_status' => 'pending', 'fulfillment_status' => 'awaiting_payment']);
        DB::table('orders')->whereIn('status', ['paid', 'fulfilled', 'returned'])->update(['payment_status' => 'paid']);
        DB::table('orders')->where('status', 'fulfilled')->update(['fulfillment_status' => 'fulfilled']);
        DB::table('orders')->where('status', 'cancelled')->update(['fulfillment_status' => 'cancelled']);
        DB::table('orders')->where('status', 'payment_expired')->update(['payment_status' => 'expired', 'fulfillment_status' => 'cancelled']);
        DB::table('orders')->where('status', 'payment_failed')->update(['payment_status' => 'failed', 'fulfillment_status' => 'cancelled']);
    }

    public function down(): void
    {
        Schema::dropIfExists('return_items'); Schema::dropIfExists('returns'); Schema::dropIfExists('order_notes'); Schema::dropIfExists('shipments'); Schema::dropIfExists('order_events');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['assigned_user_id']); $table->dropUnique(['guest_access_token']);
            $table->dropIndex(['payment_status', 'fulfillment_status', 'shipment_status']);
            $table->dropColumn(['payment_status', 'fulfillment_status', 'shipment_status', 'guest_access_token', 'assigned_user_id', 'cancel_reason', 'cancelled_at']);
        });
    }
};
