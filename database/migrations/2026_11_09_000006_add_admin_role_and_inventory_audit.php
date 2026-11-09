<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false)->index());
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity_before')->nullable();
            $table->unsignedInteger('quantity_after')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['quantity_before', 'quantity_after']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
