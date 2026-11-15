<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContentPageController extends Controller
{
    public function edit(string $slug)
    {
        abort_unless(in_array($slug, $this->slugs(), true), 404);
        $page = ContentPage::firstOrCreate(['slug' => $slug], ['title' => $this->titles()[$slug], 'status' => 'draft']);

        return view('admin.content-pages.form', compact('page'));
    }

    public function update(Request $request, string $slug)
    {
        abort_unless(in_array($slug, $this->slugs(), true), 404);
        $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'body' => ['nullable', 'string', 'max:50000'], 'status' => ['required', 'in:draft,needs_review,approved,published'], 'meta_title' => ['nullable', 'string', 'max:255'], 'meta_description' => ['nullable', 'string', 'max:320']]);
        if ($data['status'] === 'published' && blank($data['body'])) {
            return back()->withErrors(['body' => 'صفحه بدون محتوای تأییدشده منتشر نمی‌شود.'])->withInput();
        }
        $page = ContentPage::firstOrCreate(['slug' => $slug], ['title' => $this->titles()[$slug]]);
        $page->update($data + ['updated_by' => $request->user()->id, 'reviewed_at' => $data['status'] === 'approved' || $data['status'] === 'published' ? now() : null]);
        Cache::forget('storefront:published-content-pages');

        return back()->with('status', 'صفحه ذخیره شد.');
    }

    /** @return array<int, string> */
    private function slugs(): array
    {
        return array_keys($this->titles());
    }

    /** @return array<string, string> */
    private function titles(): array
    {
        return ['about' => 'درباره کیدوکی', 'contact' => 'تماس با ما', 'buying-guide' => 'راهنمای خرید', 'shipping' => 'روش‌های ارسال', 'returns' => 'شرایط مرجوعی', 'privacy' => 'حریم خصوصی', 'faq' => 'پرسش‌های متداول'];
    }
}
