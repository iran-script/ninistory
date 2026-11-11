<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->uuid('payment_uuid')->nullable()->unique()->after('id');
            $table->string('currency', 3)->default('IRR')->after('provider');
            $table->unsignedBigInteger('amount_toman')->nullable()->after('amount_rials');
            $table->string('provider_authority', 160)->nullable()->after('provider_reference');
            $table->string('idempotency_key', 160)->nullable()->unique()->after('provider_authority');
            $table->timestamp('initiated_at')->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('initiated_at');
            $table->timestamp('verified_at')->nullable()->after('expires_at');
            $table->string('failure_code', 80)->nullable()->after('verified_at');
            $table->text('failure_reason')->nullable()->after('failure_code');
            $table->unsignedSmallInteger('attempt_number')->default(1)->after('failure_reason');
            $table->json('provider_metadata')->nullable()->after('attempt_number');
            $table->text('reconciliation_note')->nullable()->after('provider_metadata');
            $table->index(['order_id', 'status', 'created_at']);
            $table->index(['provider', 'status']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id(); $table->foreignId('order_id')->constrained()->cascadeOnDelete(); $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete(); $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount_toman'); $table->string('currency', 3)->default('IRR'); $table->text('reason')->nullable(); $table->string('status', 32)->default('requested'); $table->string('provider_reference', 160)->nullable()->unique(); $table->string('idempotency_key', 160)->unique(); $table->text('failure_reason')->nullable(); $table->json('metadata')->nullable(); $table->timestamp('completed_at')->nullable(); $table->timestamps();
            $table->index(['order_id', 'status']); $table->index(['payment_id', 'status']);
        });

        \DB::table('payments')->whereNull('payment_uuid')->orderBy('id')->each(function (object $payment): void { \DB::table('payments')->where('id', $payment->id)->update(['payment_uuid' => (string) \Illuminate\Support\Str::uuid(), 'amount_toman' => (int) floor(((int) $payment->amount_rials) / 10)]); });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::table('payments', function (Blueprint $table) { $table->dropUnique(['payment_uuid']); $table->dropUnique(['idempotency_key']); $table->dropIndex(['order_id', 'status', 'created_at']); $table->dropIndex(['provider', 'status']); $table->dropColumn(['payment_uuid','currency','amount_toman','provider_authority','idempotency_key','initiated_at','expires_at','verified_at','failure_code','failure_reason','attempt_number','provider_metadata','reconciliation_note']); });
    }
};
