<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\UnassignedBarcode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A product barcode waiting for a unit (stage 11, step 7). An owner gives it to one of the product's
 * units, or drops it with a reason; both are audited. Until then nothing sends the barcode.
 */
class ProductBarcodeController extends Controller
{
    public function assign(Request $request, Product $product, UnassignedBarcode $waitingBarcode): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($waitingBarcode->product_id === $product->id, 404);

        $data = $request->validate(['unit' => ['required', 'uuid']]);

        $unit = ProductUnit::query()
            ->whereIn('variation_id', $product->sellableVariations()->select('id'))
            ->where('uuid', $data['unit'])
            ->first();

        if ($unit === null) {
            return back()->with('error', __('Choose one of this product\'s units.'));
        }

        // A barcode is unique per business among live units (the product's own barcode column is no
        // unit, and is not checked here).
        if (ProductUnit::query()->where('barcode', $waitingBarcode->barcode)->where('id', '!=', $unit->id)->exists()) {
            return back()->with('error', __('That barcode is already on another unit.'));
        }

        DB::transaction(function () use ($unit, $waitingBarcode, $product): void {
            $this->audit($waitingBarcode, 'assigned', ['barcode' => $waitingBarcode->barcode, 'unit' => $unit->name, 'product' => $product->name]);
            $unit->update(['barcode' => $waitingBarcode->barcode]);
            $waitingBarcode->delete();
        });

        return back()->with('success', __('Barcode :barcode is now on the unit :unit.', ['barcode' => $waitingBarcode->barcode, 'unit' => $unit->name]));
    }

    public function drop(Request $request, Product $product, UnassignedBarcode $waitingBarcode): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_unless($waitingBarcode->product_id === $product->id, 404);

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($waitingBarcode, $product, $data): void {
            $this->audit($waitingBarcode, 'dropped', ['barcode' => $waitingBarcode->barcode, 'product' => $product->name, 'reason' => $data['reason']]);
            $waitingBarcode->delete();
        });

        return back()->with('success', __('Barcode :barcode was dropped.', ['barcode' => $waitingBarcode->barcode]));
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(UnassignedBarcode $waitingBarcode, string $outcome, array $details): void
    {
        $user = request()->user();

        AuditableEvent::dispatch(
            auditableType: UnassignedBarcode::class,
            auditableId: (int) $waitingBarcode->id,
            auditableUuid: null,
            event: AuditEvent::DELETED,
            oldValues: ['barcode' => $waitingBarcode->barcode, 'product_id' => $waitingBarcode->product_id],
            newValues: $details,
            userId: $user?->id,
            userName: $user?->name,
            userEmail: $user?->email,
            tags: ['barcode', $outcome],
            businessId: $waitingBarcode->business_id,
        );
    }
}
