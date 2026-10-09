<?php

namespace App\Http\Controllers;

use App\Actions\ConvertAbandonedCartToSale;
use App\Actions\ManageAbandonedCart;
use App\Actions\SendAbandonedCartWhatsApp;
use App\Exceptions\AbandonedCartActionException;
use App\Http\Requests\AbandonedCart\ConvertAbandonedCartRequest;
use App\Http\Requests\AbandonedCart\IndexAbandonedCartsRequest;
use App\Http\Requests\AbandonedCart\OptOutAbandonedCartRequest;
use App\Http\Requests\AbandonedCart\SendAbandonedCartWhatsAppRequest;
use App\Http\Requests\AbandonedCart\StoreAbandonedCartNoteRequest;
use App\Http\Requests\AbandonedCart\StoreAbandonedCartReminderRequest;
use App\Http\Requests\AbandonedCart\UpdateAbandonedCartRequest;
use App\Models\AbandonedCart;
use App\Models\Alert;
use App\Models\DeliveryCompany;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Abandoned website carts: the follow-up list and the single-cart screen.
 * Business rules live in the shared actions, used by the API controller too.
 */
class AbandonedCartController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexAbandonedCartsRequest $request): View
    {
        $this->authorize('viewAny', AbandonedCart::class);

        $user = $request->user();
        $filters = $request->validated();
        $shops = Shop::query()->visibleTo($user)->orderBy('name')->get(['id', 'name', 'uuid']);
        $shopId = isset($filters['shop_id']) ? (int) $filters['shop_id'] : null;

        if ($shopId !== null && ! $shops->contains('id', $shopId)) {
            abort(403);
        }

        $carts = AbandonedCart::query()
            ->visibleTo($user)
            ->filter($filters)
            ->with(['shop', 'assignee', 'sale'])
            ->withCount('items')
            ->latest('abandoned_at')
            ->paginate(20)
            ->withQueryString();

        $statistics = AbandonedCart::statisticsFor($user, $shopId);

        return view('abandoned-carts.index', compact('carts', 'shops', 'statistics'));
    }

    public function show(Request $request, AbandonedCart $abandonedCart): View
    {
        $this->authorize('view', $abandonedCart);

        $abandonedCart->load([
            'shop', 'customer', 'assignee', 'sale', 'ecommerceOrder', 'convertedBy',
            'items.product', 'items.variation', 'timeline.creator', 'reminders.creator',
        ]);

        $cart = $abandonedCart;
        $canSeeLink = $request->user()->can('viewRecoveryLink', $cart);
        $staff = User::query()
            ->where(fn ($q) => $q->whereHas('shops', fn ($s) => $s->where('shops.id', $cart->shop_id))->orWhereDoesntHave('shops'))
            ->orderBy('name')
            ->get(['id', 'name']);
        $saleSources = SaleSource::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']);
        $defaultSourceId = $saleSources->firstWhere('name', 'Abandoned Cart')?->id;
        $deliveryCompanies = DeliveryCompany::orderBy('name')->get(['id', 'name']);

        return view('abandoned-carts.show', compact('cart', 'canSeeLink', 'staff', 'saleSources', 'defaultSourceId', 'deliveryCompanies'));
    }

    public function update(UpdateAbandonedCartRequest $request, AbandonedCart $abandonedCart, ManageAbandonedCart $manage): RedirectResponse
    {
        $this->authorize('update', $abandonedCart);

        return $this->attempt(function () use ($request, $abandonedCart, $manage): RedirectResponse {
            $manage->update($abandonedCart, $request->validated(), $request->user());

            return back()->with('success', 'Cart updated.');
        });
    }

    public function storeNote(StoreAbandonedCartNoteRequest $request, AbandonedCart $abandonedCart, ManageAbandonedCart $manage): RedirectResponse
    {
        $this->authorize('update', $abandonedCart);

        $manage->addNote($abandonedCart, $request->validated('message'), $request->user());

        return back()->with('success', 'Note added successfully.');
    }

    public function storeReminder(StoreAbandonedCartReminderRequest $request, AbandonedCart $abandonedCart, ManageAbandonedCart $manage): RedirectResponse
    {
        $this->authorize('update', $abandonedCart);

        $manage->addReminder(
            $abandonedCart,
            $request->validated('title'),
            $request->validated('message'),
            $request->validated('scheduled_at'),
            $request->user(),
        );

        return back()->with('success', 'Reminder set successfully.');
    }

    public function resolveReminder(Request $request, AbandonedCart $abandonedCart, Alert $alert, ManageAbandonedCart $manage): RedirectResponse
    {
        $this->authorize('update', $abandonedCart);

        try {
            $manage->resolveReminder($abandonedCart, $alert, $request->user());
        } catch (AbandonedCartActionException) {
            abort(403);
        }

        return back()->with('success', 'Reminder dismissed.');
    }

    public function sendWhatsApp(SendAbandonedCartWhatsAppRequest $request, AbandonedCart $abandonedCart, SendAbandonedCartWhatsApp $send): RedirectResponse
    {
        $this->authorize('contact', $abandonedCart);

        return $this->attempt(function () use ($request, $abandonedCart, $send): RedirectResponse {
            $send->execute($abandonedCart, $request->user(), $request->validated('message'));

            return back()->with('success', 'WhatsApp message sent to the customer.');
        });
    }

    public function convert(ConvertAbandonedCartRequest $request, AbandonedCart $abandonedCart, ConvertAbandonedCartToSale $convert): RedirectResponse
    {
        $this->authorize('convert', $abandonedCart);

        return $this->attempt(function () use ($request, $abandonedCart, $convert): RedirectResponse {
            $sale = $convert->execute($abandonedCart, $request->validated(), $request->user());

            return redirect()
                ->route('sales.show', $sale)
                ->with('success', "Cart converted to sale {$sale->invoice_number}.");
        });
    }

    public function optOut(OptOutAbandonedCartRequest $request, AbandonedCart $abandonedCart, ManageAbandonedCart $manage): RedirectResponse
    {
        $this->authorize('update', $abandonedCart);

        $manage->optOut($abandonedCart, $request->user(), $request->validated('reason'));

        return back()->with('success', 'Customer opted out. The website is being told to stop reminders.');
    }

    private function attempt(callable $action): RedirectResponse
    {
        try {
            return $action();
        } catch (AbandonedCartActionException $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
