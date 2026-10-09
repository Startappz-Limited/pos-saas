<?php

namespace App\Http\Controllers;

use App\Models\LowStockAlert;
use App\Models\Shop;
use App\Services\AlertService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LowStockAlertController extends Controller
{
    public function __construct(
        private readonly AlertService $alertService,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', LowStockAlert::class);

        $query = LowStockAlert::query()->with(['shop', 'product', 'variation', 'acknowledger']);

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->input('shop_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $alerts = $query->latest()->paginate($request->input('per_page', 15));

        return $this->renderFilteredList($alerts);
    }

    public function show(LowStockAlert $lowStockAlert): View
    {
        $this->authorize('view', $lowStockAlert);

        $lowStockAlert->load(['shop', 'product', 'variation', 'creator', 'acknowledger']);

        return view('low-stock-alerts.show', compact('lowStockAlert'));
    }

    public function acknowledge(Request $request, LowStockAlert $lowStockAlert): RedirectResponse
    {
        $this->authorize('acknowledge', $lowStockAlert);

        if ($lowStockAlert->isAcknowledged()) {
            return back()->with('error', __('Alert already acknowledged.'));
        }

        // Delegate to the model so `status` moves to ACKNOWLEDGED too — updating
        // only the timestamp left the alert in `pending`, so it kept appearing in
        // pending lists and could be acknowledged repeatedly.
        $lowStockAlert->acknowledge($request->user()->id);

        return back()->with('success', __('Alert acknowledged successfully.'));
    }

    public function resolve(LowStockAlert $lowStockAlert): RedirectResponse
    {
        $this->authorize('update', $lowStockAlert);

        if ($lowStockAlert->isResolved()) {
            return back()->with('error', __('Alert already resolved.'));
        }

        $lowStockAlert->resolve();

        return back()->with('success', __('Alert resolved successfully.'));
    }

    public function ignore(LowStockAlert $lowStockAlert): RedirectResponse
    {
        $this->authorize('update', $lowStockAlert);

        if ($lowStockAlert->isIgnored()) {
            return back()->with('error', __('Alert already ignored.'));
        }

        $lowStockAlert->ignore();

        return back()->with('success', __('Alert ignored successfully.'));
    }

    public function pending(Request $request): View
    {
        $this->authorize('viewAny', LowStockAlert::class);

        return $this->renderFilteredList(
            $this->alertService->getPendingAlerts($request->integer('per_page') ?: 15)
        );
    }

    public function active(Request $request): View
    {
        $this->authorize('viewAny', LowStockAlert::class);

        return $this->renderFilteredList(
            $this->alertService->getActiveAlerts($request->integer('per_page') ?: 15)
        );
    }

    /**
     * Re-scan stock levels for the acting user's shop and raise any new alerts.
     */
    public function checkLevels(Request $request): RedirectResponse
    {
        $this->authorize('create', LowStockAlert::class);

        $shopId = $request->user()->shop_id ?? Shop::first()?->id;

        if (! $shopId) {
            return back()->with('error', __('No shop available to check stock levels for.'));
        }

        $created = $this->alertService->checkLowStockLevels($shopId, $request->user()->id);

        return back()->with('success', trans_choice(
            '{0}No new low stock alerts found.|{1}:count new low stock alert raised.|[2,*]:count new low stock alerts raised.',
            $created->count(),
            ['count' => $created->count()]
        ));
    }

    /**
     * The pending/active listings reuse the index view, so they need the same
     * filter and statistics payload it expects.
     *
     * @param  LengthAwarePaginator<int, LowStockAlert>  $alerts
     */
    private function renderFilteredList($alerts): View
    {
        $shops = Shop::active()->select('id', 'name')->orderBy('name')->get();

        return view('low-stock-alerts.index', [
            'alerts' => $alerts,
            'shops' => $shops,
            'statistics' => $this->alertStatistics(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function alertStatistics(): array
    {
        $stats = LowStockAlert::query()->selectRaw("
            COUNT(*) as total_alerts,
            SUM(CASE WHEN status IN ('pending', 'acknowledged') THEN 1 ELSE 0 END) as active_alerts,
            SUM(CASE WHEN status = 'acknowledged' THEN 1 ELSE 0 END) as acknowledged_alerts
        ")->first();

        return [
            'total_alerts' => (int) $stats->total_alerts,
            'active_alerts' => (int) $stats->active_alerts,
            'acknowledged_alerts' => (int) $stats->acknowledged_alerts,
        ];
    }
}
