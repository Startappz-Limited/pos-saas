<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Supplier;
use App\Services\ProductService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ProductService $productService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->getAllProducts(
            perPage: $request->get('per_page', 15),
            search: $request->get('search'),
            status: $request->get('status'),
            categoryId: $request->get('category_id'),
            shopId: $request->get('shop_id')
        );

        $statistics = $this->productService->getStatistics();

        return view('products.index', compact('products', 'statistics'));
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        $categories = Category::active()->ordered()->get();
        $suppliers = Supplier::active()->orderBy('name')->get();
        $shops = Shop::active()->orderBy('name')->get();

        return view('products.create', compact('categories', 'suppliers', 'shops'));
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = $this->productService->createProduct($request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product created successfully.');
    }

    public function show(Product $product): View
    {
        $this->authorize('view', $product);

        $product->load(['category', 'supplier', 'shops', 'variations', 'creator', 'updater']);

        return view('products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        $product->load(['variations', 'shops']);
        $categories = Category::active()->ordered()->get();
        $suppliers = Supplier::active()->orderBy('name')->get();
        $shops = Shop::active()->orderBy('name')->get();

        return view('products.edit', compact('product', 'categories', 'suppliers', 'shops'));
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->productService->updateProduct($product, $request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $this->productService->deleteProduct($product);

        return redirect()
            ->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    public function activate(Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->productService->activateProduct($product);

        return back()->with('success', 'Product activated successfully.');
    }

    public function deactivate(Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $this->productService->deactivateProduct($product);

        return back()->with('success', 'Product deactivated successfully.');
    }

    public function lowStock(Request $request): View
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->getLowStockProducts(
            perPage: $request->get('per_page', 15)
        );

        return view('products.low-stock', compact('products'));
    }
}
