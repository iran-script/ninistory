<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cancellation_status', 24)->default('not_cancelled')->after('shipment_status');
            $table->string('refund_status', 24)->default('not_requested')->after('cancellation_status');
            $table->string('return_status', 24)->default('not_requested')->after('refund_status');
            $table->index(['cancellation_status', 'refund_status', 'return_status']);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->string('idempotency_key', 140)->nullable()->unique()->after('tracking_code');
            $table->index(['order_id', 'status']);
        });

        Schema::table('returns', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('user_id');
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_by');
            $table->timestamp('inspected_at')->nullable()->after('received_at');
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['inspected_by']);
            $table->dropIndex(['order_id', 'status']);
            $table->dropColumn(['approved_by', 'inspected_by', 'inspected_at']);
        });
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropIndex(['order_id', 'status']);
            $table->dropColumn('idempotency_key');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['cancellation_status', 'refund_status', 'return_status']);
            $table->dropColumn(['cancellation_status', 'refund_status', 'return_status']);
        });
    }
};
