<?php

namespace App\Http\Controllers\Api;

use App\Actions\ConvertEcommerceOrderToSale;
use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\EcommerceOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConvertOrderToSaleRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Jobs\FetchEcommerceOrdersJob;
use App\Jobs\SyncOrderStatusJob;
use App\Models\Alert;
use App\Models\EcommerceOrder;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EcommerceOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $selectedShopId = $request->integer('shop_id') ?: null;

        if ($selectedShopId !== null && ! $user->canAccessShop($selectedShopId)) {
            abort(403);
        }

        $query = EcommerceOrder::query()
            ->visibleTo($user)
            ->when($selectedShopId, fn ($query): mixed => $query->where('shop_id', $selectedShopId))
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

        $orders = $query->paginate($request->input('per_page', 20));

        $statisticsQuery = DB::table('ecommerce_orders')
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as pending', [EcommerceOrderStatus::Pending->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as processing', [EcommerceOrderStatus::Processing->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) as completed', [EcommerceOrderStatus::Completed->value]);

        $statistics = (array) $this->applyShopFilter($statisticsQuery, $user, $selectedShopId)->first();

        return response()->json([
            'success' => true,
            'data' => [
                'orders' => $orders,
                'statistics' => $statistics,
            ],
        ]);
    }

    public function show(EcommerceOrder $order): JsonResponse
    {
        abort_unless(auth()->user()->canAccessShop($order->shop_id), 403);

        $order->load(['shop', 'sale', 'convertedBy', 'items.product', 'orderNotes.creator', 'reminders.creator']);

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            // Plain exists: another shop's id is the explicit 403 below
            'shop_id' => ['required', 'exists:shops,id'],
        ]);

        abort_unless($request->user()->canAccessShop((int) $request->shop_id), 403);

        $shop = Shop::findOrFail($request->shop_id);
        $enabledIntegrations = $shop->getEnabledIntegrations();

        if (empty($enabledIntegrations)) {
            return response()->json([
                'success' => false,
                'message' => 'No e-commerce integrations enabled for this shop.',
            ], 422);
        }

        foreach ($enabledIntegrations as $platform) {
            FetchEcommerceOrdersJob::dispatch($shop, $platform);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order refresh has been queued. New orders will appear shortly.',
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, EcommerceOrder $order): JsonResponse
    {
        abort_unless($request->user()->canAccessShop($order->shop_id), 403);

        if (! $order->status->canChangeStatus()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change status of this order.',
            ], 422);
        }

        $order->update(['status' => $request->validated('status')]);

        try {
            SyncOrderStatusJob::dispatchSync($order, $request->validated('status'), $request->validated('note'));

            return response()->json([
                'success' => true,
                'message' => 'Order status updated and synced to platform.',
                'data' => $order,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'message' => 'Order status updated locally, but platform sync failed: '.$e->getMessage(),
                'data' => $order,
            ]);
        }
    }

    public function convert(ConvertOrderToSaleRequest $request, EcommerceOrder $order, ConvertEcommerceOrderToSale $action): JsonResponse
    {
        abort_unless($request->user()->canAccessShop($order->shop_id), 403);

        if (! $order->can_be_converted) {
            return response()->json([
                'success' => false,
                'message' => 'This order cannot be converted to a sale.',
            ], 422);
        }

        try {
            $sale = $action->execute($order, $request->validated(), auth()->user());

            $sale->load(['customer', 'items.product', 'items.variation', 'source']);

            return response()->json([
                'success' => true,
                'message' => "Order {$order->order_number} has been converted to sale {$sale->invoice_number}.",
                'data' => ['sale' => $sale],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert order: '.$e->getMessage(),
            ], 500);
        }
    }

    public function storeNote(Request $request, EcommerceOrder $order): JsonResponse
    {
        abort_unless($request->user()->canAccessShop($order->shop_id), 403);

        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $alert = Alert::create([
            'shop_id' => $order->shop_id,
            'title' => 'Note on Order #'.$order->order_number,
            'message' => $request->message,
            'type' => AlertType::ORDER_NOTE,
            'severity' => AlertSeverity::LOW,
            'category' => AlertCategory::ORDERS,
            'alertable_type' => EcommerceOrder::class,
            'alertable_id' => $order->id,
            'is_read' => true,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Note added successfully.',
            'data' => $alert,
        ], 201);
    }

    public function storeReminder(Request $request, EcommerceOrder $order): JsonResponse
    {
        abort_unless($request->user()->canAccessShop($order->shop_id), 403);

        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
        ]);

        $alert = Alert::create([
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

        return response()->json([
            'success' => true,
            'message' => 'Reminder set successfully.',
            'data' => $alert,
        ], 201);
    }

    public function resolveReminder(EcommerceOrder $order, Alert $alert): JsonResponse
    {
        abort_unless(auth()->user()->canAccessShop($order->shop_id), 403);

        if ($alert->alertable_id !== $order->id || $alert->alertable_type !== EcommerceOrder::class) {
            return response()->json([
                'success' => false,
                'message' => 'This reminder does not belong to this order.',
            ], 403);
        }

        $alert->resolve('Dismissed by user', auth()->id());

        return response()->json([
            'success' => true,
            'message' => 'Reminder dismissed.',
        ]);
    }

    private function applyShopFilter(QueryBuilder $query, User $user, ?int $shopId = null): QueryBuilder
    {
        if ($shopId !== null) {
            return $query->where('shop_id', $shopId);
        }

        if ($user->hasShopRestrictions()) {
            return $query->whereIn('shop_id', $user->accessibleShopIds());
        }

        return $query;
    }
}
