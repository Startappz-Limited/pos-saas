<?php

namespace App\Jobs;

use App\Models\AbandonedCart;
use App\Models\Role;
use App\Models\Shop;
use App\Notifications\NewAbandonedCartNotification;
use App\Services\Integration\AbandonedCartSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Applies one Abandoned Cart Recovery plugin webhook (topic "acr_*") to the POS.
 *
 * Dispatched by WebhookController only after the WooCommerce signature has been
 * verified. Safe to run more than once for the same delivery.
 */
class ProcessAbandonedCartWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Shop $shop,
        public string $topic,
        public array $payload
    ) {
        $this->onQueue('ecommerce');
    }

    public function handle(AbandonedCartSyncService $sync): void
    {
        $topic = AbandonedCartSyncService::resolveTopic($this->topic, $this->payload);

        Log::info('Processing abandoned-cart webhook', [
            'shop_id' => $this->shop->id,
            'topic' => $topic,
            'cart_id' => $this->payload['id'] ?? null,
        ]);

        if (in_array($topic, AbandonedCartSyncService::CAPTURE_TOPICS, true)) {
            // An email was captured before the cut-off; the cart is not abandoned yet.
            return;
        }

        match (true) {
            $topic === 'acr_cart.cutoff' => $this->handleCutoff($sync),
            $topic === 'acr_cart.recovered' => $sync->markRecovered($this->shop, $topic, $this->payload),
            in_array($topic, AbandonedCartSyncService::REMINDER_TOPICS, true) => $sync->recordReminder($this->shop, $topic, $this->payload),
            $topic === 'acr_link.clicked' => $sync->recordClick($this->shop, $topic, $this->payload),
            default => Log::info('Abandoned-cart webhook ignored: unhandled topic', ['shop_id' => $this->shop->id, 'topic' => $topic]),
        };
    }

    private function handleCutoff(AbandonedCartSyncService $sync): void
    {
        $cart = $sync->upsertFromCutoff($this->shop, $this->payload);

        if ($cart?->wasRecentlyCreated) {
            $this->notifyShopUsers($cart);
        }
    }

    /**
     * Tell the shop's manager (and other managers / admins / super-admins on the shop)
     * about a new abandoned cart, falling back to the shop's own contact details.
     * Mirrors ProcessOrderWebhookJob::notifyShopUsers(); a failure here must never
     * fail the webhook.
     */
    protected function notifyShopUsers(AbandonedCart $cart): void
    {
        try {
            $cart->loadMissing(['shop', 'items']);

            $notification = new NewAbandonedCartNotification($cart);
            $notified = 0;

            $manager = $this->shop->manager;

            if ($manager) {
                $manager->notify($notification);
                $notified++;
            }

            // whereHas rather than ->role(): role() throws if any listed role is
            // missing, which would silently skip every recipient.
            $others = $this->shop->users()
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['manager', Role::ADMIN, Role::SUPER_ADMIN]))
                ->where('users.id', '!=', $manager?->id)
                ->get();

            foreach ($others as $user) {
                $user->notify($notification);
                $notified++;
            }

            if ($notified === 0) {
                if ($this->shop->email || $this->shop->phone) {
                    $route = Notification::route('mail', $this->shop->email);

                    if ($this->shop->phone) {
                        $route = $route->route('sms', $this->shop->phone);
                    }

                    Notification::send($route, $notification);
                } else {
                    Log::warning('No recipients for abandoned-cart notification', [
                        'cart_id' => $cart->id,
                        'shop_id' => $this->shop->id,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send new abandoned-cart notification', [
                'cart_id' => $cart->id,
                'shop_id' => $this->shop->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
