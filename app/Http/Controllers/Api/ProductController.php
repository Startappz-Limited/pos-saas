<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(protected ProductService $productService) {}

    public function index(Request $request): JsonResponse
    {
        $products = $this->productService->getAllProducts(
            perPage: $request->input('per_page', 15),
            search: $request->input('search'),
            status: $request->input('status'),
            categoryId: $request->input('category_id'),
            shopId: $request->input('shop_id'),
        );

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['created_by'] = auth()->id();
        $product = $this->productService->createProduct($validated);
        $product->load(['category', 'supplier', 'variations']);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => $product,
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'supplier', 'variations', 'shops']);

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku,' . $product->id],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['sometimes', 'integer', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'track_stock' => ['nullable', 'boolean'],
            'unit' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['updated_by'] = auth()->id();
        $product = $this->productService->updateProduct($product, $validated);
        $product->load(['category', 'supplier', 'variations']);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => $product,
        ]);
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->productService->deleteProduct($product);

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }

    public function activate(Product $product): JsonResponse
    {
        $product = $this->productService->activateProduct($product);

        return response()->json([
            'success' => true,
            'message' => 'Product activated successfully.',
            'data' => $product,
        ]);
    }

    public function deactivate(Product $product): JsonResponse
    {
        $product = $this->productService->deactivateProduct($product);

        return response()->json([
            'success' => true,
            'message' => 'Product deactivated successfully.',
            'data' => $product,
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
