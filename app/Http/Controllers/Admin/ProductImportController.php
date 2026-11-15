<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductCsvImportService;
use Illuminate\Http\Request;

class ProductImportController extends Controller
{
    public function create()
    {
        return view('admin.products.import');
    }

    public function preview(Request $request, ProductCsvImportService $importer)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);
        $result = $importer->preview($request->file('file')->getRealPath());
        session(['product_import_rows' => $result['rows']->values()->all()]);

        return view('admin.products.import', ['rows' => $result['rows'], 'importErrors' => $result['errors']]);
    }

    public function store(Request $request, ProductCsvImportService $importer)
    {
        $rows = collect(session('product_import_rows', []));
        abort_if($rows->isEmpty(), 422, 'ابتدا پیش‌نمایش معتبر بسازید.');
        $count = $importer->import($rows);
        $request->session()->forget('product_import_rows');

        return redirect()->route('admin.products.index')->with('status', "{$count} تنوع محصول به‌صورت پیش‌نویس وارد شد.");
    }
}
