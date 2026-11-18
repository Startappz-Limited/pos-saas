<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Models\CostLayer;
use App\Models\StockRelease;
use App\Services\Stock\QuarantineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Quarantined stock (stage 11, quarantine policy): an inspection list of what is held, where it can be verified
 * or released, and the release history. Viewing needs stock.view; verifying and releasing need
 * stock.release-quarantine (the business owner has it through the super-admin role).
 */
class StockQuarantineController extends Controller
{
    public function __construct(protected QuarantineService $quarantine) {}

    public function index(): View
    {
        $this->authorize('stock.view');

        $layers = CostLayer::query()
            ->with(['product:id,name,is_medical', 'variation:id,name', 'shop:id,name'])
            ->whereNotNull('quarantine_status')
            ->where('remaining_quantity', '>', 0)
            ->orderBy('received_at')->orderBy('id')
            ->paginate(25);

        $blocked = $layers->getCollection()->mapWithKeys(fn (CostLayer $layer): array => [$layer->id => $this->quarantine->blockedReason($layer)]);

        $releases = StockRelease::query()
            ->with(['product:id,name', 'approver:id,name'])
            ->latest('approved_at')->limit(25)->get();

        // Each layer's base-unit precision, so the release form only offers quantities the unit allows.
        $precisions = $layers->getCollection()->mapWithKeys(fn (CostLayer $layer): array => [$layer->id => $this->quarantine->baseUnitPrecision($layer)]);

        return view('stock-quarantine.index', [
            'layers' => $layers,
            'blocked' => $blocked,
            'precisions' => $precisions,
            'releases' => $releases,
        ]);
    }

    public function verify(Request $request, CostLayer $costLayer): RedirectResponse
    {
        $this->authorize('stock.release-quarantine');

        $data = $request->validate([
            'batch_number' => ['required', 'string', 'max:100'],
            'expiry_date' => ['required', 'date'],
        ]);

        try {
            $this->quarantine->verify($costLayer, $request->user(), $data['batch_number'], $data['expiry_date']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Batch and expiry verified.'));
    }

    public function release(Request $request, CostLayer $costLayer): RedirectResponse
    {
        $this->authorize('stock.release-quarantine');

        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.001'],
            'reason' => ['required', 'string', 'max:500'],
            'request_key' => ['required', 'string', 'max:64'],
        ]);

        try {
            [, $replayed] = $this->quarantine->release($costLayer, $request->user(), (string) $data['quantity'], $data['reason'], $data['request_key']);
        } catch (BusinessRuleException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $replayed ? __('This release was already recorded.') : __('Stock released for sale.'));
    }
}
