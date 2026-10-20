<?php

namespace App\Http\Controllers;

use App\Actions\Product\ImportProductsFromCsv;
use App\Models\Product;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV product import (stage 3, onboarding): all or nothing, errors by line.
 */
class ProductImportController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.import', ['shops' => Shop::active()->orderBy('name')->get()]);
    }

    public function store(Request $request, ImportProductsFromCsv $import): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $shop = Shop::query()->findOrFail($validated['shop_id']);
        $result = $import->execute($request->file('file')->getRealPath(), $shop, $request->user()->id);

        if ($result['errors'] !== []) {
            return back()->withInput($request->except('file'))->with('import_errors', $result['errors']);
        }

        return redirect()->route('products.index')
            ->with('success', trans_choice(':count product imported.|:count products imported.', $result['imported'], ['count' => $result['imported']]));
    }

    public function template(): StreamedResponse
    {
        $this->authorize('create', Product::class);

        return response()->streamDownload(fn () => print ImportProductsFromCsv::template(), 'products-template.csv', ['Content-Type' => 'text/csv']);
    }
}
