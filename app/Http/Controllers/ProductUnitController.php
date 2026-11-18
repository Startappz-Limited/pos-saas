<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Rules\QuantityForUnit;
use App\Rules\UniqueForTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The units of a product's variations (stage 11, step 2): a page to add, change and remove the
 * units a product is sold and bought in. The model keeps the rules (one base unit, a used unit's
 * factor locked, the base unit kept); this page shows what the model refuses.
 */
class ProductUnitController extends Controller
{
    public function index(Product $product): View
    {
        $this->authorize('update', $product);

        $variations = $product->sellableVariations()->with(['units' => fn ($units) => $units->orderBy('sort_order')->orderBy('id')])->orderBy('id')->get();

        return view('products.units', compact('product', 'variations'));
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $data = $this->validated($request, $product);
        $variation = $this->variationOf($product, $data['variation']);

        try {
            ProductUnit::query()->create([
                'variation_id' => $variation->id,
                'name' => $data['name'],
                'factor' => $data['factor'],
                'precision' => $data['precision'],
                'barcode' => $data['barcode'] ?? null,
                'selling_price' => $data['selling_price'] ?? null,
                'wholesale_price' => $data['wholesale_price'] ?? null,
                'can_sell' => $request->boolean('can_sell', true),
                'can_buy' => $request->boolean('can_buy', true),
                'is_base' => false,
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Unit :name added.', ['name' => $data['name']]));
    }

    public function update(Request $request, Product $product, ProductUnit $unit): RedirectResponse
    {
        $this->authorize('update', $product);
        $this->ownUnit($product, $unit);

        $data = $this->validated($request, $product, $unit);

        // The base unit's pack size is always 1: it isn't sent to the model at all.
        $packSize = $unit->is_base ? [] : ['factor' => $data['factor']];

        try {
            $unit->fill([
                'name' => $data['name'],
                ...$packSize,
                'precision' => $data['precision'],
                'barcode' => $data['barcode'] ?? null,
                'selling_price' => $data['selling_price'] ?? null,
                'wholesale_price' => $data['wholesale_price'] ?? null,
                'can_sell' => $request->boolean('can_sell'),
                'can_buy' => $request->boolean('can_buy'),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ])->save();
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Unit :name saved.', ['name' => $unit->name]));
    }

    public function destroy(Product $product, ProductUnit $unit): RedirectResponse
    {
        $this->authorize('update', $product);
        $this->ownUnit($product, $unit);

        try {
            $unit->delete();
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Unit :name removed. Past sales still show it.', ['name' => $unit->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Product $product, ?ProductUnit $unit = null): array
    {
        return $request->validate([
            'variation' => [$unit === null ? 'required' : 'nullable', 'uuid'],
            'name' => ['required', 'string', 'max:60'],
            // A factor is stored at scale 3 (`Quantity`), so it must be at least 0.001 and have no more
            // decimals: anything smaller would be stored as 0 and remove no stock on sale.
            'factor' => ['required', 'numeric', 'min:0.001', 'max:1000000', new QuantityForUnit(3)],
            'precision' => ['required', 'integer', 'between:0,3'],
            'barcode' => ['nullable', 'string', 'max:100', (new UniqueForTenant(ProductUnit::class, 'barcode'))->ignore($unit?->id)],
            'selling_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    /**
     * The product's own variation the unit is added to: a simple product's hidden default one, or one
     * of its real variations.
     */
    private function variationOf(Product $product, string $uuid): ProductVariation
    {
        $variation = $product->sellableVariations()->where('uuid', $uuid)->first();

        abort_if($variation === null, 404);

        return $variation;
    }

    private function ownUnit(Product $product, ProductUnit $unit): void
    {
        $belongs = $product->sellableVariations()->whereKey($unit->variation_id)->exists();

        abort_unless($belongs, 404);
    }
}
