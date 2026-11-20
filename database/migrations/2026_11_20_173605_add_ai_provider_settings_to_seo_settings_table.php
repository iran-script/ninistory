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
        Schema::table('seo_settings', function (Blueprint $table): void {
            $table->boolean('ai_enabled')->default(false);
            $table->string('ai_provider', 40)->default('chabokan');
            $table->text('ai_api_key')->nullable();
            $table->string('ai_model', 160)->default('openai/gpt-4o-mini');
            $table->unsignedSmallInteger('ai_max_output_length')->default(1600);
            $table->unsignedSmallInteger('ai_timeout_seconds')->default(30);
            $table->string('ai_default_tone', 120)->default('دوستانه، حرفه‌ای و مناسب فروشگاه پوشاک کودک');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seo_settings', function (Blueprint $table): void {
            $table->dropColumn(['ai_enabled', 'ai_provider', 'ai_api_key', 'ai_model', 'ai_max_output_length', 'ai_timeout_seconds', 'ai_default_tone']);
        });
    }
};
