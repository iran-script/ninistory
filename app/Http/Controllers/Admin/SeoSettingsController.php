<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeoSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.seo.edit', ['settings' => SeoSetting::query()->firstOrCreate([], ['site_name' => 'نینی‌استوری', 'tagline' => 'قصه‌های قشنگ کوچولوها'])]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['site_name' => ['required', 'string', 'max:160'], 'tagline' => ['required', 'string', 'max:200'], 'default_title' => ['nullable', 'string', 'max:255'], 'default_description' => ['nullable', 'string', 'max:320'], 'organization_name' => ['nullable', 'string', 'max:160'], 'search_console_verification' => ['nullable', 'alpha_num', 'max:100'], 'analytics_measurement_id' => ['nullable', 'regex:/^G-[A-Z0-9]+$/', 'max:30'], 'indexing_enabled' => ['sometimes', 'boolean']]);
        $data['indexing_enabled'] = $request->boolean('indexing_enabled');
        SeoSetting::query()->firstOrCreate([])->update($data);

        return back()->with('status', 'تنظیمات سئو ذخیره شد.');
    }
}
