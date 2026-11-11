<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void { Schema::table('orders', function(Blueprint $table){ $table->foreignId('shipping_address_id')->nullable()->after('user_id')->constrained('addresses')->nullOnDelete(); $table->index('shipping_address_id'); }); } public function down(): void { Schema::table('orders', fn(Blueprint $table)=>$table->dropConstrainedForeignId('shipping_address_id')); } };
