<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Rules\UniqueForTenant;
use App\Services\ProductService;
use App\Support\Products\CostFields;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $products = $this->productService->getAllProducts(
            perPage: $request->input('per_page', 15),
            search: $request->input('search'),
            status: $request->input('status'),
            categoryId: $request->input('category_id'),
            shopId: $request->input('shop_id'),
        );

        return response()->json([
            'success' => true,
            'data' => CostFields::forViewer($products, $request->user()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Product::class);

        // Products with variations are made on the web (stage 10, step 1f): never silently made simple.
        if ($request->filled('variations') || $request->boolean('has_variations')) {
            return response()->json([
                'success' => false,
                'message' => __('Create products with variations on the web.'),
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:100', new UniqueForTenant(Product::class, 'sku')],
            // Required: products.category_id is NOT NULL, so a product without one failed with a 500.
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        // Without products.set-cost the product is made with no cost ("cost unknown").
        if (! $request->user()->can('setCost', Product::class)) {
            $validated['cost_price'] = null;
        }

        $validated['created_by'] = auth()->id();
        $product = $this->productService->createProduct($validated);
        $product->load(['category', 'supplier', 'variations']);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => CostFields::forViewer($product, $request->user()),
        ], 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load(['category', 'supplier', 'variations', 'shops']);

        return response()->json([
            'success' => true,
            'data' => CostFields::forViewer($product, $request->user()),
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:100', (new UniqueForTenant(Product::class, 'sku'))->ignore($product)],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        // Changing the purchase cost needs products.set-cost; sending the cost unchanged is fine.
        if (array_key_exists('cost_price', $validated) && ! $request->user()->can('setCost', Product::class)) {
            $current = $product->cost_price === null ? null : round((float) $product->cost_price, 2);
            $sent = $validated['cost_price'] === null ? null : round((float) $validated['cost_price'], 2);

            if ($sent !== $current) {
                return response()->json(['success' => false, 'message' => __("You can't set purchase costs.")], 403);
            }
            unset($validated['cost_price']);
        }

        $validated['updated_by'] = auth()->id();
        $product = $this->productService->updateProduct($product, $validated);
        $product->load(['category', 'supplier', 'variations']);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => CostFields::forViewer($product, $request->user()),
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);

        $this->productService->deleteProduct($product);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    public function activate(Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product = $this->productService->activateProduct($product);

        return response()->json([
            'success' => true,
            'message' => 'Product activated successfully.',
            'data' => CostFields::forViewer($product, auth()->user()),
        ]);
    }

    public function deactivate(Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $product = $this->productService->deactivateProduct($product);

        return response()->json([
            'success' => true,
            'message' => 'Product deactivated successfully.',
            'data' => CostFields::forViewer($product, auth()->user()),
        ]);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $products = $this->productService->getLowStockProducts(
            perPage: $request->input('per_page', 15)
        );

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }
}
