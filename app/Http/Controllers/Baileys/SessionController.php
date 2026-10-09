<?php

namespace App\Http\Controllers\Baileys;

use App\Actions\Baileys\DisconnectBaileysSession;
use App\Actions\Baileys\StartBaileysSession;
use App\Enums\BaileysSessionStatus;
use App\Http\Controllers\Controller;
use App\Models\BaileysSession;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Startappz\WaGateway\Exceptions\GatewayException;
use Startappz\WaGateway\WaGateway;

class SessionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected WaGateway $gateway) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', BaileysSession::class);

        $sessions = BaileysSession::query()
            ->with(['shop:id,name', 'creator:id,name'])
            ->when($request->filled('shop_id'), fn ($q) => $q->where('shop_id', $request->integer('shop_id')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $shops = Shop::query()->select('id', 'name')->orderBy('name')->get();

        // Shops the user may actually toggle automation for — deliberately
        // narrower than the filter list, which is display-only.
        $automationShops = Shop::query()
            ->visibleTo($request->user())
            ->select('id', 'uuid', 'name', 'settings')
            ->orderBy('name')
            ->get();

        return view('baileys.sessions.index', compact('sessions', 'shops', 'automationShops'));
    }

    /**
     * Turn one of a shop's sale-notification switches on or off.
     *
     * `all` is the master switch — with it off no channel fires, but each
     * channel keeps its own position for when it is switched back on.
     */
    public function toggleSaleNotifications(Request $request, Shop $shop): RedirectResponse
    {
        $this->authorize('create', BaileysSession::class);
        abort_unless($request->user()->canAccessShop($shop->id), 403);

        $validated = $request->validate([
            'channel' => ['required', 'string', Rule::in(Shop::SALE_NOTIFY_CHANNELS)],
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['enabled'];
        $shop->setSaleNotification($validated['channel'], $enabled);
        $shop->save();

        $label = match ($validated['channel']) {
            Shop::SALE_NOTIFY_INVOICE_PDF => __('Invoice PDF'),
            Shop::SALE_NOTIFY_TEXT_RECEIPT => __('Text receipt'),
            default => __('All sale messages'),
        };

        return redirect()
            ->back()
            ->with('success', $enabled
                ? __(':setting turned on for :shop.', ['setting' => $label, 'shop' => $shop->name])
                : __(':setting turned off for :shop.', ['setting' => $label, 'shop' => $shop->name]));
    }

    public function create(): View
    {
        $this->authorize('create', BaileysSession::class);

        $shops = Shop::query()->select('id', 'name')->orderBy('name')->get();

        return view('baileys.sessions.create', compact('shops'));
    }

    public function store(Request $request, StartBaileysSession $action): RedirectResponse
    {
        $this->authorize('create', BaileysSession::class);

        $data = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $shop = Shop::findOrFail($data['shop_id']);
        $session = $action->execute($shop, auth()->id(), $data['name'] ?? 'Default');

        return redirect()->route('baileys.sessions.show', $session)
            ->with('success', 'Baileys session initialised. Scan the QR code with WhatsApp to connect.');
    }

    public function show(BaileysSession $session): View
    {
        $this->authorize('view', $session);

        // Refresh QR if it has expired or is missing while still pending
        if (
            in_array($session->status, [BaileysSessionStatus::Pending, BaileysSessionStatus::QrReady], true)
            && (! $session->qr_code || ($session->qr_expires_at && $session->qr_expires_at->isPast()))
        ) {
            // Best-effort: with the gateway unreachable the page still renders,
            // and the session.qr webhook updates the QR once it is back.
            try {
                $qr = $this->gateway->qr($session->session_key);
            } catch (GatewayException) {
                $qr = null;
            }

            if (! empty($qr['qr'])) {
                $session->update([
                    'qr_code' => $qr['qr'],
                    'qr_expires_at' => isset($qr['expires_at'])
                        ? Carbon::parse($qr['expires_at'])
                        : now()->addMinute(),
                    'status' => BaileysSessionStatus::QrReady,
                ]);
                $session->refresh();
            }
        }

        return view('baileys.sessions.show', [
            'session' => $session->load('shop:id,name', 'creator:id,name'),
        ]);
    }

    public function destroy(BaileysSession $session, DisconnectBaileysSession $action): RedirectResponse
    {
        $this->authorize('delete', $session);

        $isAlreadyDisconnected = in_array(
            $session->status,
            [BaileysSessionStatus::Disconnected, BaileysSessionStatus::Failed],
            true,
        );

        if ($isAlreadyDisconnected) {
            $session->delete();

            return redirect()->route('baileys.sessions.index')
                ->with('success', 'Baileys session deleted.');
        }

        try {
            $action->execute($session, 'Disconnected via admin');
        } catch (GatewayException $e) {
            return back()->with('error', __('Could not reach the WhatsApp gateway to unlink this number: :reason Try again shortly.', [
                'reason' => $e->getMessage(),
            ]));
        }

        return redirect()->route('baileys.sessions.index')
            ->with('success', 'Baileys session disconnected. Click delete to remove it.');
    }
}
