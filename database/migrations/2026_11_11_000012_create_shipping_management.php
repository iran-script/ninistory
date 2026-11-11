<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('base_fee_toman')->default(0);
            $table->string('estimated_delivery', 120)->nullable();
            $table->unsignedInteger('max_weight_grams')->nullable();
            $table->unsignedBigInteger('min_order_toman')->nullable();
            $table->unsignedBigInteger('max_order_toman')->nullable();
            $table->boolean('free_shipping_eligible')->default(false);
            $table->boolean('requires_address')->default(true);
            $table->json('configuration')->nullable();
            $table->timestamps();
            $table->index(['is_enabled', 'sort_order']);
        });

        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->string('province_code', 20)->nullable();
            $table->string('city_code', 40)->nullable();
            $table->unsignedBigInteger('fee_toman')->nullable();
            $table->unsignedBigInteger('free_over_toman')->nullable();
            $table->unsignedBigInteger('weight_surcharge_toman')->default(0);
            $table->unsignedInteger('weight_step_grams')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->index(['shipping_method_id', 'province_code', 'city_code']);
        });

        Schema::create('shipping_provinces', function (Blueprint $table) {
            $table->id(); $table->string('code', 20)->unique(); $table->string('name', 100); $table->boolean('is_enabled')->default(true); $table->timestamps();
        });
        Schema::create('shipping_cities', function (Blueprint $table) {
            $table->id(); $table->foreignId('province_id')->constrained('shipping_provinces')->cascadeOnDelete(); $table->string('code', 40); $table->string('name', 100); $table->boolean('is_enabled')->default(true); $table->timestamps(); $table->unique(['province_id', 'code']);
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('nickname', 80)->nullable()->after('user_id');
            $table->string('building', 120)->nullable()->after('address');
            $table->string('province_code', 20)->nullable()->after('province');
            $table->string('city_code', 40)->nullable()->after('city');
            $table->index(['user_id', 'is_default']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->string('shipping_method_code', 40)->nullable()->after('shipping_toman');
            $table->string('shipping_method_name', 120)->nullable()->after('shipping_method_code');
            $table->json('shipping_rate_snapshot')->nullable()->after('shipping_method_name');
            $table->json('shipping_address_snapshot')->nullable()->after('shipping_rate_snapshot');
        });

        $methods = [
            ['code' => 'post_pishtaz', 'name' => 'پست پیشتاز', 'description' => 'نرخ نمونه؛ پیش از بهره‌برداری نرخ واقعی را تنظیم کنید.', 'base_fee_toman' => 85000, 'estimated_delivery' => '۲ تا ۴ روز کاری'],
            ['code' => 'post_sefareshi', 'name' => 'پست سفارشی', 'description' => 'نرخ نمونه؛ پیش از بهره‌برداری نرخ واقعی را تنظیم کنید.', 'base_fee_toman' => 65000, 'estimated_delivery' => '۳ تا ۷ روز کاری'],
            ['code' => 'tipax', 'name' => 'تیپاکس', 'description' => 'نرخ نمونه؛ پیش از بهره‌برداری نرخ واقعی را تنظیم کنید.', 'base_fee_toman' => 120000, 'estimated_delivery' => '۲ تا ۵ روز کاری'],
            ['code' => 'city_courier', 'name' => 'پیک شهری', 'description' => 'فقط در شهرهای تحت پوشش پیک.', 'base_fee_toman' => 70000, 'estimated_delivery' => 'همان روز یا روز کاری بعد'],
            ['code' => 'pickup', 'name' => 'تحویل حضوری', 'description' => 'تحویل رایگان از محل فروشگاه پس از هماهنگی.', 'base_fee_toman' => 0, 'estimated_delivery' => 'پس از آماده‌سازی', 'requires_address' => false],
        ];
        foreach ($methods as $sort => $method) {
            DB::table('shipping_methods')->updateOrInsert(['code' => $method['code']], array_merge($method, ['sort_order' => $sort + 1, 'is_enabled' => true, 'free_shipping_eligible' => $method['code'] === 'pickup', 'created_at' => now(), 'updated_at' => now()]));
        }

        $locations = [
            ['code' => 'tehran', 'name' => 'تهران', 'cities' => [['code' => 'tehran', 'name' => 'تهران'], ['code' => 'rey', 'name' => 'ری'], ['code' => 'shemiranat', 'name' => 'شمیرانات']]],
            ['code' => 'alborz', 'name' => 'البرز', 'cities' => [['code' => 'karaj', 'name' => 'کرج'], ['code' => 'fardis', 'name' => 'فردیس']]],
            ['code' => 'isfahan', 'name' => 'اصفهان', 'cities' => [['code' => 'isfahan', 'name' => 'اصفهان']]],
            ['code' => 'fars', 'name' => 'فارس', 'cities' => [['code' => 'shiraz', 'name' => 'شیراز']]],
        ];
        foreach ($locations as $province) {
            $provinceId = DB::table('shipping_provinces')->insertGetId(['code' => $province['code'], 'name' => $province['name'], 'created_at' => now(), 'updated_at' => now()]);
            foreach ($province['cities'] as $city) DB::table('shipping_cities')->insert(['province_id' => $provinceId, 'code' => $city['code'], 'name' => $city['name'], 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn(['shipping_method_code', 'shipping_method_name', 'shipping_rate_snapshot', 'shipping_address_snapshot']));
        Schema::table('addresses', fn (Blueprint $table) => $table->dropColumn(['nickname', 'building', 'province_code', 'city_code']));
        Schema::dropIfExists('shipping_rates'); Schema::dropIfExists('shipping_methods'); Schema::dropIfExists('shipping_cities'); Schema::dropIfExists('shipping_provinces');
    }
};
