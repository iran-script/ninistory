<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('seo_settings')
            ->where('site_name', 'نینی‌استوری')
            ->update(['site_name' => 'کیدوکی']);
    }

    public function down(): void
    {
        DB::table('seo_settings')
            ->where('site_name', 'کیدوکی')
            ->update(['site_name' => 'نینی‌استوری']);
    }
};
