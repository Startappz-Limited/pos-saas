<?php

namespace App\Services;

use App\Actions\Product\CreateProductAction;
use App\Actions\Product\DeleteProductAction;
use App\Actions\Product\UpdateProductAction;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Stock\StockQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        private CreateProductAction $createAction,
        private UpdateProductAction $updateAction,
        private DeleteProductAction $deleteAction
    ) {}

    public function getAllProducts(int $perPage = 15, ?string $search = null, ?string $status = null, ?int $categoryId = null, ?int $shopId = null, bool $waitingBarcodesOnly = false): LengthAwarePaginator
    {
        $query = Product::query()->with(['category', 'creator', 'updater', 'shops'])->withStockOnHand();

        // Products with a barcode waiting for a unit (stage 11, step 7).
        if ($waitingBarcodesOnly) {
            $query->whereHas('unassignedBarcodes');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    // A unit's own barcode finds its product exactly: a crate's code is not a bottle's.
                    ->orWhereHas('sellableVariations.units', fn ($units) => $units->where('product_units.barcode', $search));
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
            ->withStockOnHand()
            ->orderBy('stock_on_hand')
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
            // Stock, not a status: active products that track stock and hold none in the viewer's shops (stage 11, step 6).
            'waiting_barcodes' => Product::query()->whereHas('unassignedBarcodes')->count(),
            'out_of_stock' => Product::active()->where('track_stock', true)->whereStock('<=', 0)->count(),
            'discontinued' => Product::discontinued()->count(),
            'low_stock' => Product::lowStock()->count(),
            // Units in the shops the viewer may access (per-shop stock, stage 1b).
            'total_stock_value' => (int) DB::table('shop_variation')
                ->whereIn('product_id', Product::active()->select('products.id'))
                ->whereIn('shop_id', app(StockQuery::class)->shops())
                ->sum('stock_quantity'),
        ];
    }
}
