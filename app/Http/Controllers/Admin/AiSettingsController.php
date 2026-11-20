<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SeoSetting;
use App\Services\Ai\ChabokanAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AiSettingsController extends Controller
{
    public function edit(): View
    {
        $settings = SeoSetting::query()->firstOrCreate([], ['site_name' => 'کیدوکی', 'tagline' => 'پوشاک کودک']);

        return view('admin.ai-settings.edit', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = SeoSetting::query()->firstOrCreate([], ['site_name' => 'کیدوکی', 'tagline' => 'پوشاک کودک']);
        $data = $request->validate([
            'ai_enabled' => ['sometimes', 'boolean'],
            'ai_model' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'ai_api_key' => ['nullable', 'string', 'max:4096'],
            'remove_api_key' => ['sometimes', 'boolean'],
            'ai_max_output_length' => ['required', 'integer', 'min:800', 'max:8000'],
            'ai_timeout_seconds' => ['required', 'integer', 'min:5', 'max:90'],
            'ai_default_tone' => ['required', 'string', 'max:120'],
        ]);
        $data['ai_enabled'] = $request->boolean('ai_enabled');
        $data['ai_provider'] = 'chabokan';
        if ($request->boolean('remove_api_key')) {
            $data['ai_api_key'] = null;
        } elseif (blank($data['ai_api_key'] ?? null)) {
            unset($data['ai_api_key']);
        }
        unset($data['remove_api_key']);
        if ($data['ai_enabled'] && blank($data['ai_api_key'] ?? $settings->ai_api_key)) {
            return back()->withErrors(['ai_api_key' => 'برای فعال‌سازی، ابتدا کلید API را ثبت کنید.'])->withInput($request->except('ai_api_key'));
        }
        $settings->update($data);

        return back()->with('status', 'تنظیمات هوش مصنوعی ذخیره شد.');
    }

    public function test(ChabokanAiService $ai): JsonResponse
    {
        $settings = SeoSetting::query()->first();
        if (! $settings) {
            return response()->json(['message' => 'تنظیمات هوش مصنوعی ثبت نشده است.'], 422);
        }
        try {
            $milliseconds = $ai->testConnection($settings);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['message' => 'اتصال موفق است.', 'model' => $settings->ai_model, 'response_time_ms' => $milliseconds]);
    }

    public function generate(Request $request, ChabokanAiService $ai): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'material' => ['nullable', 'string', 'max:160'],
            'composition' => ['nullable', 'string', 'max:240'],
            'age_guidance' => ['nullable', 'string', 'max:160'],
            'season' => ['nullable', 'string', 'max:100'],
            'fit_information' => ['nullable', 'string', 'max:5000'],
            'brand' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'short_description' => ['nullable', 'string', 'max:500'],
            'features' => ['nullable', 'array', 'max:20'],
            'features.*' => ['string', 'max:160'],
            'colors' => ['nullable', 'array', 'max:30'],
            'colors.*' => ['string', 'max:80'],
            'sizes' => ['nullable', 'array', 'max:30'],
            'sizes.*' => ['string', 'max:40'],
        ]);
        $settings = SeoSetting::query()->first();
        if (! $settings) {
            return response()->json(['message' => 'تنظیمات هوش مصنوعی ثبت نشده است.'], 422);
        }
        $category = isset($validated['category_id']) ? Category::query()->whereKey($validated['category_id'])->value('name') : null;
        $approvedProductDetails = [
            'name' => $validated['name'],
            'category' => $category,
            'material' => $validated['material'] ?? null,
            'composition' => $validated['composition'] ?? null,
            'age_guidance' => $validated['age_guidance'] ?? null,
            'season' => $validated['season'] ?? null,
            'fit_information' => $validated['fit_information'] ?? null,
            'brand' => $validated['brand'] ?? 'Kidoki',
            'description' => $validated['description'] ?? null,
            'short_description' => $validated['short_description'] ?? null,
            'features' => $validated['features'] ?? [],
            'colors' => $validated['colors'] ?? [],
            'sizes' => $validated['sizes'] ?? [],
        ];
        try {
            return response()->json(['suggestions' => $ai->generateProductSeo($settings, $approvedProductDetails)]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
