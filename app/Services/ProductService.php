<?php

namespace App\Services;

use App\Actions\Product\CreateProductAction;
use App\Actions\Product\DeleteProductAction;
use App\Actions\Product\UpdateProductAction;
use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductService
{
    public function __construct(
        private CreateProductAction $createAction,
        private UpdateProductAction $updateAction,
        private DeleteProductAction $deleteAction
    ) {}

    public function getAllProducts(int $perPage = 15, ?string $search = null, ?string $status = null, ?int $categoryId = null, ?int $shopId = null): LengthAwarePaginator
    {
        $query = Product::query()->with(['category', 'creator', 'updater', 'shops']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        if ($categoryId) {
            $query->byCategory($categoryId);
        }

        if ($shopId) {
            $query->whereHas('shops', function ($q) use ($shopId) {
                $q->where('shops.id', $shopId);
            });
        }

        return $query->latest()->paginate($perPage);
    }

    public function getLowStockProducts(int $perPage = 15): LengthAwarePaginator
    {
        return Product::query()
            ->lowStock()
            ->active()
            ->with('category')
            ->orderBy('stock_quantity')
            ->paginate($perPage);
    }

    public function getOutOfStockProducts(): Collection
    {
        return Product::query()
            ->outOfStock()
            ->with('category')
            ->get();
    }

    public function createProduct(array $data): Product
    {
        return $this->createAction->execute($data);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        return $this->updateAction->execute($product, $data);
    }

    public function deleteProduct(Product $product): bool
    {
        return $this->deleteAction->execute($product);
    }

    public function activateProduct(Product $product): Product
    {
        return $this->updateProduct($product, ['status' => ProductStatus::ACTIVE]);
    }

    public function deactivateProduct(Product $product): Product
    {
        return $this->updateProduct($product, ['status' => ProductStatus::INACTIVE]);
    }

    public function markOutOfStock(Product $product): Product
    {
        return $this->updateProduct($product, ['status' => ProductStatus::OUT_OF_STOCK]);
    }

    public function markDiscontinued(Product $product): Product
    {
        return $this->updateProduct($product, ['status' => ProductStatus::DISCONTINUED]);
    }

    public function adjustStock(Product $product, int $quantity, string $type = 'add'): Product
    {
        $newQuantity = $type === 'add'
            ? $product->stock_quantity + $quantity
            : $product->stock_quantity - $quantity;

        return $this->updateProduct($product, ['stock_quantity' => max(0, $newQuantity)]);
    }

    public function getStatistics(): array
    {
        return [
            'total' => Product::count(),
            'active' => Product::active()->count(),
            'inactive' => Product::inactive()->count(),
            'out_of_stock' => Product::outOfStock()->count(),
            'discontinued' => Product::discontinued()->count(),
            'low_stock' => Product::lowStock()->count(),
            'total_stock_value' => Product::active()->sum('stock_quantity'),
        ];
    }
}
