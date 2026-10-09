<?php

namespace App\Http\Controllers;

use App\Actions\ConvertEcommerceOrderToSale;
use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\EcommerceOrderStatus;
use App\Http\Requests\ConvertOrderToSaleRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Jobs\FetchEcommerceOrdersJob;
use App\Jobs\SyncOrderStatusJob;
use App\Models\Alert;
use App\Models\CashRegister;
use App\Models\DeliveryCompany;
use App\Models\EcommerceOrder;
use App\Models\Product;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EcommerceOrderController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of e-commerce orders.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', EcommerceOrder::class);

        $user = $request->user();
        $availableShops = $this->availableShops($user);
        $selectedShopId = $this->selectedShopId($request, $availableShops);

        $query = EcommerceOrder::query()
            ->visibleTo($user)
            ->when($selectedShopId, fn($query): mixed => $query->where('shop_id', $selectedShopId))
            ->with(['shop', 'sale'])
            ->withCount('items')
            ->latest('platform_created_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhere('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('platform')) {
            $query->platform($request->platform);
        }

        if ($request->filled('status')) {
            $query->status($request->status);
        }

        if ($request->filled('conversion')) {
            if ($request->conversion === 'converted') {
                $query->converted();
            } elseif ($request->conversion === 'unconverted') {
                $query->unconverted();
            }
        }

        $orders = $query->paginate(20);

        // Show shops that have orders OR have integrations configured
        $shops = Shop::query()
            ->visibleTo($user)
            ->where(function ($q) {
                $q->whereIn('id', EcommerceOrder::select('shop_id')->distinct())
                    ->orWhere('settings->integrations', '!=', null);
            })
            ->get(['id', 'name', 'uuid', 'settings']);

        $statisticsQuery = DB::table('ecommerce_orders')
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as pending', [EcommerceOrderStatus::Pending->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as processing', [EcommerceOrderStatus::Processing->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as completed', [EcommerceOrderStatus::Completed->value]);

        $statistics = (array) $this->applyShopFilter($statisticsQuery, $user, $selectedShopId)->first();

        return view('orders.index', compact('orders', 'shops', 'statistics'));
    }

    /**
     * Display the specified order.
     */
    public function show(EcommerceOrder $order): View
    {
        $this->authorize('view', $order);

        $order->load(['shop', 'sale', 'convertedBy', 'items.product', 'orderNotes.creator', 'reminders.creator']);

        return view('orders.show', compact('order'));
    }

    /**
     * Refresh orders from platform API.
     */
    public function refresh(Request $request): RedirectResponse
    {
        $this->authorize('refresh', EcommerceOrder::class);

        $request->validate([
            'shop_id' => ['required', 'exists:shops,id'],
        ]);

        abort_unless($request->user()->canAccessShop((int) $request->shop_id), 403);

        $shop = Shop::findOrFail($request->shop_id);
        $enabledIntegrations = $shop->getEnabledIntegrations();

        if (empty($enabledIntegrations)) {
            return back()->with('error', 'No e-commerce integrations enabled for this shop.');
        }

        foreach ($enabledIntegrations as $platform) {
            FetchEcommerceOrdersJob::dispatch($shop, $platform);
        }

        return back()->with('success', 'Order refresh has been queued. New orders will appear shortly.');
    }

    /**
     * Update order status and sync back to platform.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, EcommerceOrder $order): RedirectResponse
    {
        $this->authorize('updateStatus', $order);

        if (! $order->status->canChangeStatus()) {
            return back()->with('error', 'Cannot change status of this order.');
        }

        // Update local status immediately
        $order->update(['status' => $request->validated('status')]);

        // Sync to platform in real-time (synchronous)
        try {
            SyncOrderStatusJob::dispatchSync($order, $request->validated('status'), $request->validated('note'));

            return back()->with('success', 'Order status updated and synced to platform.');
        } catch (\Exception $e) {
            return back()->with('warning', 'Order status updated locally, but platform sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Show the conversion form.
     */
    public function convertToSale(EcommerceOrder $order): View
    {
        $this->authorize('convert', $order);

        if (! $order->can_be_converted) {
            abort(403, 'This order cannot be converted to a sale.');
        }

        $order->load(['shop', 'items.product']);

        $activeRegister = CashRegister::getActiveRegister();
        $websiteSource = SaleSource::where('name', 'Website')->first();
        $deliveryCompanies = DeliveryCompany::orderBy('name')->get();
        $products = Product::with('variations')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('orders.convert', compact('order', 'activeRegister', 'websiteSource', 'deliveryCompanies', 'products'));
    }

    /**
     * Convert the e-commerce order to a local sale.
     */
    public function storeConversion(ConvertOrderToSaleRequest $request, EcommerceOrder $order, ConvertEcommerceOrderToSale $action): RedirectResponse
    {
        $this->authorize('convert', $order);

        if (! $order->can_be_converted) {
            return back()->with('error', 'This order cannot be converted to a sale.');
        }

        try {
            $sale = $action->execute($order, $request->validated(), auth()->user());

            return redirect()
                ->route('sales.show', $sale)
                ->with('success', "Order {$order->order_number} has been converted to sale {$sale->invoice_number}.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to convert order: ' . $e->getMessage());
        }
    }

    /**
     * Add a note to the order.
     */
    public function storeNote(Request $request, EcommerceOrder $order): RedirectResponse
    {
        $this->authorize('view', $order);

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        Alert::create([
            'shop_id' => $order->shop_id,
            'title' => 'Note on Order #' . $order->order_number,
            'message' => $request->message,
            'type' => AlertType::ORDER_NOTE,
            'severity' => AlertSeverity::LOW,
            'category' => AlertCategory::ORDERS,
            'alertable_type' => EcommerceOrder::class,
            'alertable_id' => $order->id,
            'is_read' => true,
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Note added successfully.');
    }

    /**
     * Add a reminder to the order.
     */
    public function storeReminder(Request $request, EcommerceOrder $order): RedirectResponse
    {
        $this->authorize('view', $order);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        Alert::create([
            'shop_id' => $order->shop_id,
            'title' => $request->title,
            'message' => $request->message ?? '',
            'type' => AlertType::ORDER_REMINDER,
            'severity' => AlertSeverity::MEDIUM,
            'category' => AlertCategory::ORDERS,
            'alertable_type' => EcommerceOrder::class,
            'alertable_id' => $order->id,
            'scheduled_at' => $request->scheduled_at,
            'data' => [
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
            ],
            'created_by' => auth()->id(),
        ]);

        return back()->with('success', 'Reminder set successfully.');
    }

    /**
     * Resolve/dismiss a reminder.
     */
    public function resolveReminder(EcommerceOrder $order, Alert $alert): RedirectResponse
    {
        $this->authorize('view', $order);

        if ($alert->alertable_id !== $order->id || $alert->alertable_type !== EcommerceOrder::class) {
            abort(403);
        }

        $alert->resolve('Dismissed by user', auth()->id());

        return back()->with('success', 'Reminder dismissed.');
    }

    /**
     * Download the order invoice as PDF (public signed URL).
     */
    public function invoicePdf(EcommerceOrder $order): HttpResponse
    {
        $order->load(['shop', 'items']);
        $shop = $order->shop;

        $pdf = Pdf::loadView('pdf.order-invoice', compact('order', 'shop'));

        return $pdf->download("invoice-{$order->order_number}.pdf");
    }

    /**
     * @return Collection<int, Shop>
     */
    private function availableShops(User $user): EloquentCollection
    {
        return Shop::query()
            ->visibleTo($user)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function selectedShopId(Request $request, EloquentCollection $shops): ?int
    {
        $shopId = $request->integer('shop_id') ?: null;

        if ($shopId !== null && ! $shops->contains('id', $shopId)) {
            abort(403);
        }

        return $shopId;
    }

    private function applyShopFilter(QueryBuilder $query, User $user, ?int $shopId = null): QueryBuilder
    {
        if ($shopId !== null) {
            return $query->where('shop_id', $shopId);
        }

        if ($user->hasShopRestrictions()) {
            return $query->whereIn('shop_id', $user->assignedShopIds());
        }

        return $query;
    }
}
