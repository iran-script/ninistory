<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SeoAuditService;
use Illuminate\View\View;

class SeoAuditController extends Controller
{
    public function __invoke(SeoAuditService $audit): View
    {
        return view('admin.seo.audit', ['findings' => $audit->findings()]);
    }
}
