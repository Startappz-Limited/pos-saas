<?php

namespace App\Http\Controllers\Api;

use App\Actions\ConvertAbandonedCartToSale;
use App\Actions\ManageAbandonedCart;
use App\Actions\SendAbandonedCartWhatsApp;
use App\Exceptions\AbandonedCartActionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AbandonedCart\ConvertAbandonedCartRequest;
use App\Http\Requests\AbandonedCart\IndexAbandonedCartsRequest;
use App\Http\Requests\AbandonedCart\OptOutAbandonedCartRequest;
use App\Http\Requests\AbandonedCart\SendAbandonedCartWhatsAppRequest;
use App\Http\Requests\AbandonedCart\StoreAbandonedCartNoteRequest;
use App\Http\Requests\AbandonedCart\StoreAbandonedCartReminderRequest;
use App\Http\Requests\AbandonedCart\UpdateAbandonedCartRequest;
use App\Http\Resources\AbandonedCartDetailResource;
use App\Http\Resources\AbandonedCartResource;
use App\Models\AbandonedCart;
use App\Models\Alert;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abandoned website carts for the mobile app — the same screens and rules as
 * the web UI (see App\Http\Controllers\AbandonedCartController), sharing the
 * ManageAbandonedCart / ConvertAbandonedCartToSale / SendAbandonedCartWhatsApp
 * actions. New endpoints only; nothing existing changed shape.
 */
class AbandonedCartController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexAbandonedCartsRequest $request): JsonResponse
    {
        $this->authorize('viewAny', AbandonedCart::class);

        $user = $request->user();
        $filters = $request->validated();
        $shopId = isset($filters['shop_id']) ? (int) $filters['shop_id'] : null;

        if ($shopId !== null && ! $user->canAccessShop($shopId)) {
            abort(403);
        }

        $carts = AbandonedCart::query()
            ->visibleTo($user)
            ->filter($filters)
            ->with(['shop', 'customer', 'assignee', 'sale'])
            ->withCount('items')
            ->latest('abandoned_at')
            ->paginate($filters['per_page'] ?? 20)
            ->through(fn (AbandonedCart $cart) => (new AbandonedCartResource($cart))->resolve($request));

        return response()->json([
            'success' => true,
            'data' => [
                'carts' => $carts,
                'statistics' => AbandonedCart::statisticsFor($user, $shopId),
            ],
        ]);
    }

    public function show(Request $request, AbandonedCart $abandonedCart): JsonResponse
    {
        $this->authorize('view', $abandonedCart);

        return $this->detail($request, $abandonedCart);
    }

    public function update(UpdateAbandonedCartRequest $request, AbandonedCart $abandonedCart, ManageAbandonedCart $manage): JsonResponse
    {
        $this->authorize('update', $abandonedCart);

        return $this->attempt(function () use ($request, $abandonedCart, $manage): JsonResponse {
            $manage->update($abandonedCart, $request->validated(), $request->user());

            return $this->detail($request, $abandonedCart, 'Cart updated.');
        });
    }

    public function storeNote(StoreAbandonedCartNoteRequest $request, AbandonedCart $abandonedCart, ManageAbandonedCart $manage): JsonResponse
    {
        $this->authorize('update', $abandonedCart);

        $alert = $manage->addNote($abandonedCart, $request->validated('message'), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Note added successfully.',
            'data' => AbandonedCartDetailResource::alert($alert->load('creator')),
        ], 201);
    }

    public function storeReminder(StoreAbandonedCartReminderRequest $request, AbandonedCart $abandonedCart, ManageAbandonedCart $manage): JsonResponse
    {
        $this->authorize('update', $abandonedCart);

        $alert = $manage->addReminder(
            $abandonedCart,
            $request->validated('title'),
            $request->validated('message'),
            $request->validated('scheduled_at'),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Reminder set successfully.',
            'data' => AbandonedCartDetailResource::alert($alert->load('creator')),
        ], 201);
    }

    public function resolveReminder(Request $request, AbandonedCart $abandonedCart, Alert $alert, ManageAbandonedCart $manage): JsonResponse
    {
        $this->authorize('update', $abandonedCart);

        return $this->attempt(function () use ($request, $abandonedCart, $alert, $manage): JsonResponse {
            $manage->resolveReminder($abandonedCart, $alert, $request->user());

            return response()->json(['success' => true, 'message' => 'Reminder dismissed.']);
        }, 403);
    }

    public function sendWhatsApp(SendAbandonedCartWhatsAppRequest $request, AbandonedCart $abandonedCart, SendAbandonedCartWhatsApp $send): JsonResponse
    {
        $this->authorize('contact', $abandonedCart);

        return $this->attempt(function () use ($request, $abandonedCart, $send): JsonResponse {
            $message = $send->execute($abandonedCart, $request->user(), $request->validated('message'));

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp message sent to the customer.',
                'data' => [
                    'message' => [
                        'id' => $message->id,
                        'status' => $message->status->value,
                    ],
                    'cart' => (new AbandonedCartResource($abandonedCart->refresh()))->resolve($request),
                ],
            ]);
        });
    }

    public function convert(ConvertAbandonedCartRequest $request, AbandonedCart $abandonedCart, ConvertAbandonedCartToSale $convert): JsonResponse
    {
        $this->authorize('convert', $abandonedCart);

        return $this->attempt(function () use ($request, $abandonedCart, $convert): JsonResponse {
            $sale = $convert->execute($abandonedCart, $request->validated(), $request->user());
            $sale->load(['customer', 'items.product', 'items.variation', 'source']);

            return response()->json([
                'success' => true,
                'message' => "Cart converted to sale {$sale->invoice_number}.",
                'data' => ['sale' => $sale],
            ], 201);
        });
    }

    public function optOut(OptOutAbandonedCartRequest $request, AbandonedCart $abandonedCart, ManageAbandonedCart $manage): JsonResponse
    {
        $this->authorize('update', $abandonedCart);

        $manage->optOut($abandonedCart, $request->user(), $request->validated('reason'));

        return $this->detail($request, $abandonedCart, 'Customer opted out. The website is being told to stop reminders.');
    }

    private function detail(Request $request, AbandonedCart $cart, ?string $message = null): JsonResponse
    {
        $cart->refresh()->load([
            'shop', 'customer', 'assignee', 'sale', 'ecommerceOrder',
            'items.product', 'timeline.creator', 'reminders.creator',
        ]);

        return response()->json(array_filter([
            'success' => true,
            'message' => $message,
            'data' => (new AbandonedCartDetailResource($cart))->resolve($request),
        ], fn ($value) => $value !== null));
    }

    /**
     * Run an action, turning a refusal the user can fix into a 422 (or $status).
     */
    private function attempt(callable $action, int $status = 422): JsonResponse
    {
        try {
            return $action();
        } catch (AbandonedCartActionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }
}
