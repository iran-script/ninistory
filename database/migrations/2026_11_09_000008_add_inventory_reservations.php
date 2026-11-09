<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('reserved_quantity')->default(0)->after('stock_quantity');
            $table->index('reserved_quantity');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_key', 100)->nullable()->unique()->after('number');
        });

        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->index();
            $table->string('idempotency_key', 140)->unique();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['order_id', 'status']);
            $table->index(['product_variant_id', 'status']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->string('movement_type', 32)->default('legacy_adjustment')->index();
            $table->unsignedInteger('physical_before')->nullable();
            $table->unsignedInteger('physical_after')->nullable();
            $table->unsignedInteger('reserved_before')->nullable();
            $table->unsignedInteger('reserved_after')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('stock_reservations')->nullOnDelete();
            $table->string('idempotency_key', 140)->nullable()->unique();
            $table->index(['movement_type', 'created_at']);
        });

        // Legacy records remain intact; these fields make their physical audit meaning explicit.
        DB::table('inventory_movements')->whereNull('physical_before')->update([
            'physical_before' => DB::raw('quantity_before'),
            'physical_after' => DB::raw('quantity_after'),
        ]);
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
            $table->dropForeign(['reservation_id']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn(['movement_type', 'physical_before', 'physical_after', 'reserved_before', 'reserved_after', 'order_id', 'reservation_id', 'idempotency_key']);
        });
        Schema::dropIfExists('stock_reservations');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['checkout_key']);
            $table->dropColumn('checkout_key');
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex(['reserved_quantity']);
            $table->dropColumn('reserved_quantity');
        });
    }
};
