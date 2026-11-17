<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->unsignedTinyInteger('duration_seconds')->default(6)->after('sort_order');
            $table->string('text_position', 20)->default('right')->after('duration_seconds');
            $table->string('overlay_style', 20)->default('soft')->after('text_position');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table): void {
            $table->dropColumn(['duration_seconds', 'text_position', 'overlay_style']);
        });
    }
};
