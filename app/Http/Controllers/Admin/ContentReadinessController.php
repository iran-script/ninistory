<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\ShippingRate;
use App\Services\ProductReadinessService;

class ContentReadinessController extends Controller
{
    public function __invoke(ProductReadinessService $readiness)
    {
        $pageSlugs = ['about', 'contact', 'buying-guide', 'shipping', 'returns', 'privacy', 'faq'];
        $pages = ContentPage::query()->whereIn('slug', $pageSlugs)->get()->keyBy('slug');

        return view('admin.content-readiness', [
            'counts' => $readiness->dashboardCounts(),
            'categoriesMissingGuides' => Category::query()->where('is_active', true)->whereNull('buying_guide')->count(),
            'articlesAwaitingReview' => Article::query()->whereIn('editorial_status', ['draft', 'needs_review'])->count(),
            'missingPages' => collect($pageSlugs)->filter(fn (string $slug): bool => ! $pages->has($slug)),
            'shippingRates' => ShippingRate::query()->where('is_enabled', true)->count(),
            'pages' => $pages,
        ]);
    }
}
