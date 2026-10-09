<?php

namespace App\Services\Integration;

use App\Enums\AbandonedCartStatus;
use App\Enums\AlertSeverity;
use App\Models\AbandonedCart;
use App\Models\Customer;
use App\Models\EcommerceOrder;
use App\Models\Shop;
use App\Support\PhoneNumber;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Applies Abandoned Cart Recovery plugin webhooks to the POS.
 *
 * Every handler is idempotent: WooCommerce retries failed deliveries and the
 * plugin can fire the same event more than once, so replaying a payload must
 * leave the cart exactly as one delivery would.
 *
 * Payload tolerance: ids arrive as ints or numeric strings, and the fields the
 * plugin added later (sku, quantity, price, image, cart_total, cart_status,
 * capture_source, abandoned_at, customer_name, template_name) may be missing.
 */
class AbandonedCartSyncService
{
    public const PLATFORM = 'woocommerce';

    /** Topics that capture an email before the cut-off. The POS only tracks abandoned carts. */
    public const CAPTURE_TOPICS = ['acr_cart.atc', 'acr_cart.checkout', 'acr_cart.exit', 'acr_cart.form'];

    public const REMINDER_TOPICS = ['acr_email.sent', 'acr_sms.sent', 'acr_whatsapp.sent'];

    /**
     * Payload keys that must never be stored: live, signed links to the cart.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = ['checkout_link', 'links_included'];

    public function __construct(private readonly EcommerceProductMatcher $matcher) {}

    /**
     * Resolve the effective topic: the delivery header, or the payload's own
     * resource/action pair when the header is not an `acr_` topic.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function resolveTopic(string $topic, array $payload): string
    {
        $topic = strtolower(trim($topic));

        if (str_starts_with($topic, 'acr_')) {
            return $topic;
        }

        $resource = strtolower((string) ($payload['webhook_resource'] ?? ''));
        $action = strtolower((string) ($payload['webhook_action'] ?? ''));

        return $resource !== '' && $action !== '' ? "{$resource}.{$action}" : $topic;
    }

    /**
     * Create or refresh a cart from an `acr_cart.cutoff` payload.
     *
     * Staff decisions win: an existing cart keeps its status, so a replayed or
     * late cut-off never reopens a cart someone already contacted, converted or
     * wrote off. A converted cart also keeps its lines — they are what was sold.
     *
     * @param  array<string, mixed>  $payload
     */
    public function upsertFromCutoff(Shop $shop, array $payload): ?AbandonedCart
    {
        $cartId = self::normalizeId($payload['id'] ?? $payload['webhook_resource_id'] ?? null);

        if ($cartId === null) {
            Log::warning('Abandoned-cart cut-off ignored: no cart id', ['shop_id' => $shop->id]);

            return null;
        }

        if (! empty($payload['deleted'])) {
            Log::info('Abandoned-cart cut-off ignored: cart deleted on the website', ['shop_id' => $shop->id, 'cart_id' => $cartId]);

            return null;
        }

        return DB::transaction(function () use ($shop, $payload, $cartId): AbandonedCart {
            $cart = AbandonedCart::withTrashed()
                ->where('shop_id', $shop->id)
                ->where('platform', self::PLATFORM)
                ->where('platform_cart_id', $cartId)
                ->lockForUpdate()
                ->first();

            $isNew = $cart === null;
            $cart ??= new AbandonedCart([
                'shop_id' => $shop->id,
                'platform' => self::PLATFORM,
                'platform_cart_id' => $cartId,
                'status' => AbandonedCartStatus::Abandoned,
            ]);

            if ($cart->trashed()) {
                $cart->restore();
            }

            $details = array_values(array_filter((array) ($payload['product_details'] ?? []), 'is_array'));
            $lines = $this->mapLines($shop, $details);
            $frozen = $cart->status === AbandonedCartStatus::Converted;

            $phone = self::string($payload['phone'] ?? null);
            $email = self::string($payload['email_id'] ?? $payload['email'] ?? null);
            $abandonedAt = self::parseAbandonedAt($payload);

            $attributes = [
                'platform_status' => self::string($payload['cart_status'] ?? $payload['status'] ?? null) ?? $cart->platform_status,
                'customer_name' => $this->customerName($payload) ?? $cart->customer_name,
                'customer_email' => $email ?? $cart->customer_email,
                'customer_phone' => $phone ?? $cart->customer_phone,
                'phone_normalized' => PhoneNumber::normalize($phone ?? $cart->customer_phone),
                'user_type' => self::string($payload['user_type'] ?? null) ?? $cart->user_type,
                'capture_source' => self::string($payload['capture_source'] ?? $payload['captured_by'] ?? null) ?? $cart->capture_source,
                'currency' => strtoupper(substr(self::string($payload['currency'] ?? null) ?? ($cart->currency ?: 'KES'), 0, 3)),
                'coupon_code' => self::string($payload['coupon_code'] ?? null),
                'abandoned_at' => $abandonedAt,
                'last_activity' => 'abandoned',
                'platform_data' => self::sanitize($payload),
            ];

            if (! $frozen) {
                $subtotal = round(array_sum(array_map(fn (array $d): float => self::money($d['line_subtotal'] ?? $d['total'] ?? null) ?? 0.0, $details)), 2);
                $lineTax = round(array_sum(array_map(fn (array $d): float => self::money($d['total_tax'] ?? null) ?? 0.0, $details)), 2);
                $taxTotal = self::money($payload['tax_total'] ?? null) ?? $lineTax;

                $attributes += [
                    'subtotal' => $subtotal,
                    'tax_total' => $taxTotal,
                    'total' => self::money($payload['cart_total'] ?? null)
                        ?? self::money($payload['total'] ?? null)
                        ?? round($subtotal + $taxTotal, 2),
                ];
            }

            $link = self::string($payload['checkout_link'] ?? null);

            if ($link !== null) {
                $attributes['checkout_link'] = $link;
            }

            if ($cart->customer_id === null) {
                $attributes['customer_id'] = $this->matchCustomer($shop, $phone, $email)?->id;
            }

            $cart->fill($attributes)->save();

            if (! $frozen) {
                $cart->items()->delete();
                $cart->items()->createMany($lines);
            }

            $eventKey = 'acr_cart.cutoff:'.$cartId.':'.$abandonedAt->getTimestamp();

            if (! $cart->hasActivity($eventKey)) {
                $cart->logActivity(
                    $isNew ? 'Cart abandoned on the website' : 'Cart abandoned again on the website',
                    trim(count($lines).' item(s), '.$cart->currency.' '.number_format((float) $cart->total, 2)),
                    ['event' => 'acr_cart.cutoff', 'event_key' => $eventKey],
                );
            }

            // Surface "new" to the caller the way updateOrCreate would.
            $cart->wasRecentlyCreated = $isNew;

            return $cart;
        });
    }

    /**
     * Record a reminder the website sent (email / SMS / WhatsApp).
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordReminder(Shop $shop, string $topic, array $payload): ?AbandonedCart
    {
        $cart = $this->findCart($shop, $topic, $payload);

        if ($cart === null) {
            return null;
        }

        $channel = self::string($payload['reminder_type'] ?? null) ?? str_replace('acr_', '', strtok($topic, '.'));
        $sentId = self::normalizeId($payload['sent_id'] ?? null);
        $eventKey = $topic.':'.($sentId ?? sha1((string) json_encode(self::sanitize($payload))));

        return DB::transaction(function () use ($cart, $topic, $payload, $channel, $sentId, $eventKey): AbandonedCart {
            $cart = AbandonedCart::whereKey($cart->id)->lockForUpdate()->firstOrFail();

            if ($cart->hasActivity($eventKey)) {
                return $cart;
            }

            $template = self::string($payload['template_name'] ?? null);
            $coupon = self::string($payload['coupon_code'] ?? null);

            $cart->logActivity(
                'Website sent a reminder by '.self::channelLabel($channel),
                implode(' · ', array_filter([
                    $template ? "Template: {$template}" : null,
                    $coupon ? "Coupon: {$coupon}" : null,
                ])),
                array_filter([
                    'event' => $topic,
                    'event_key' => $eventKey,
                    'channel' => $channel,
                    'sent_id' => $sentId,
                    'template_name' => $template,
                    'coupon_code' => $coupon,
                ], fn ($v) => $v !== null),
            );

            $cart->forceFill([
                'reminders_sent' => $cart->reminders_sent + 1,
                'last_activity' => "{$channel}_sent",
                'platform_status' => self::string($payload['cart_status'] ?? $payload['status'] ?? null) ?? $cart->platform_status,
            ])->save();

            return $cart;
        });
    }

    /**
     * Record that the customer clicked a link in a reminder.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordClick(Shop $shop, string $topic, array $payload): ?AbandonedCart
    {
        $cart = $this->findCart($shop, $topic, $payload);

        if ($cart === null) {
            return null;
        }

        $sentId = self::normalizeId($payload['sent_id'] ?? null);
        $clickedAt = self::timestamp($payload['time_clicked'] ?? null);
        $eventKey = $topic.':'.($sentId ?? '0').':'.($clickedAt?->getTimestamp() ?? sha1((string) json_encode(self::sanitize($payload))));

        return DB::transaction(function () use ($cart, $topic, $payload, $sentId, $clickedAt, $eventKey): AbandonedCart {
            $cart = AbandonedCart::whereKey($cart->id)->lockForUpdate()->firstOrFail();

            if ($cart->hasActivity($eventKey)) {
                return $cart;
            }

            // The link *type* (cart, checkout, shop…), never the URL itself.
            $link = self::string($payload['link_clicked'] ?? null);
            $channel = self::string($payload['reminder_type'] ?? null);

            $cart->logActivity(
                'Customer clicked '.($link ? "the {$link} link" : 'a link').' in a reminder',
                $channel ? 'From the '.self::channelLabel($channel).' reminder' : '',
                array_filter([
                    'event' => $topic,
                    'event_key' => $eventKey,
                    'link_clicked' => $link,
                    'channel' => $channel,
                    'sent_id' => $sentId,
                    'clicked_at' => $clickedAt?->toIso8601String(),
                ], fn ($v) => $v !== null),
            );

            $cart->forceFill(['last_activity' => 'link_clicked'])->save();

            return $cart;
        });
    }

    /**
     * The customer completed the purchase on the website.
     *
     * @param  array<string, mixed>  $payload
     */
    public function markRecovered(Shop $shop, string $topic, array $payload): ?AbandonedCart
    {
        $cart = $this->findCart($shop, $topic, $payload);

        if ($cart === null) {
            return null;
        }

        $orderId = self::normalizeId($payload['order_id'] ?? null);
        $eventKey = $topic.':'.($orderId ?? '0');

        return DB::transaction(function () use ($shop, $cart, $topic, $payload, $orderId, $eventKey): AbandonedCart {
            $cart = AbandonedCart::whereKey($cart->id)->lockForUpdate()->firstOrFail();
            $alreadyRecorded = $cart->hasActivity($eventKey);

            $order = $orderId !== null
                ? EcommerceOrder::where('shop_id', $shop->id)
                    ->where('platform', self::PLATFORM)
                    ->where('platform_order_id', $orderId)
                    ->first()
                : null;

            $updates = [
                'last_activity' => 'recovered',
                'platform_status' => self::string($payload['cart_status'] ?? $payload['status'] ?? null) ?? 'recovered',
                'recovered_at' => $cart->recovered_at ?? now(),
                'platform_order_id' => $orderId ?? $cart->platform_order_id,
                'ecommerce_order_id' => $order?->id ?? $cart->ecommerce_order_id,
            ];

            $converted = $cart->status === AbandonedCartStatus::Converted;

            if (! $converted) {
                $updates['status'] = AbandonedCartStatus::Recovered;
            }

            $cart->forceFill($updates)->save();

            if (! $alreadyRecorded) {
                if ($converted && $orderId !== null) {
                    // The POS already sold this cart; a website order too may be a double sale.
                    $cart->logActivity(
                        "Website reports this cart recovered as order #{$orderId}",
                        'The cart was already converted to a POS sale. Check that the customer was not charged twice.',
                        ['event' => $topic, 'event_key' => $eventKey, 'order_id' => $orderId],
                        severity: AlertSeverity::HIGH,
                    );
                } else {
                    $cart->logActivity(
                        $orderId !== null ? "Recovered online as order #{$orderId}" : 'Recovered on the website',
                        '',
                        array_filter(['event' => $topic, 'event_key' => $eventKey, 'order_id' => $orderId], fn ($v) => $v !== null),
                    );
                }
            }

            return $cart;
        });
    }

    /**
     * Link any recovered cart that points at this website order. The order and
     * the plugin's "recovered" event are delivered independently, in either order.
     */
    public function linkOrder(EcommerceOrder $order): int
    {
        if ($order->platform !== self::PLATFORM || ! $order->platform_order_id) {
            return 0;
        }

        return AbandonedCart::where('shop_id', $order->shop_id)
            ->where('platform', self::PLATFORM)
            ->where('platform_order_id', (string) $order->platform_order_id)
            ->whereNull('ecommerce_order_id')
            ->update(['ecommerce_order_id' => $order->id]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findCart(Shop $shop, string $topic, array $payload): ?AbandonedCart
    {
        $cartId = self::normalizeId($payload['id'] ?? $payload['webhook_resource_id'] ?? null);

        $cart = $cartId === null ? null : AbandonedCart::where('shop_id', $shop->id)
            ->where('platform', self::PLATFORM)
            ->where('platform_cart_id', $cartId)
            ->first();

        if ($cart === null) {
            Log::info('Abandoned-cart webhook ignored: cart not known to the POS', [
                'shop_id' => $shop->id,
                'topic' => $topic,
                'cart_id' => $cartId,
            ]);
        }

        return $cart;
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @return array<int, array<string, mixed>>
     */
    private function mapLines(Shop $shop, array $details): array
    {
        $lines = [];

        foreach ($details as $detail) {
            $quantity = max(1, (int) ($detail['quantity'] ?? 1));
            $lineTotalWithTax = (self::money($detail['total'] ?? null) ?? self::money($detail['line_subtotal'] ?? null) ?? 0.0)
                + (self::money($detail['total_tax'] ?? null) ?? 0.0);
            $unitPrice = self::money($detail['price'] ?? null) ?? round($lineTotalWithTax / $quantity, 2);

            $platformProductId = self::normalizeId($detail['product_id'] ?? null);
            $sku = self::string($detail['sku'] ?? null);
            $match = $this->matcher->matchLine($shop, self::PLATFORM, $platformProductId, $sku);

            $lines[] = [
                'platform_product_id' => $platformProductId,
                'platform_variation_id' => self::normalizeId($detail['variation_id'] ?? null),
                'product_id' => $match['product_id'],
                'variation_id' => $match['variation_id'],
                'sku' => $sku,
                'name' => mb_substr(self::string($detail['product_name'] ?? $detail['name'] ?? null) ?? 'Unknown product', 0, 255),
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $quantity, 2),
                'image_url' => self::imageUrl($detail['image'] ?? null),
            ];
        }

        return $lines;
    }

    /**
     * Match a customer of THIS shop — by phone first (the stronger key), then
     * email. Never creates one: the cart may be the only thing we know about them.
     */
    private function matchCustomer(Shop $shop, ?string $phone, ?string $email): ?Customer
    {
        if ($phone !== null && PhoneNumber::normalize($phone) !== null) {
            $customer = Customer::where('shop_id', $shop->id)->wherePhone($phone)->first();

            if ($customer) {
                return $customer;
            }
        }

        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Customer::where('shop_id', $shop->id)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function customerName(array $payload): ?string
    {
        $name = self::string($payload['customer_name'] ?? null);

        if ($name !== null) {
            return $name;
        }

        $name = trim(((string) ($payload['billing_first_name'] ?? '')).' '.((string) ($payload['billing_last_name'] ?? '')));

        return $name !== '' ? $name : null;
    }

    /**
     * The payload as stored: minus every live recovery link.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitize(array $payload): array
    {
        return Arr::except($payload, self::SENSITIVE_KEYS);
    }

    /**
     * A plugin id as a canonical string: 42, "42" and " 42 " are the same cart;
     * 0, "" and null are no id at all.
     */
    public static function normalizeId(mixed $value): ?string
    {
        if (is_int($value)) {
            return $value > 0 ? (string) $value : null;
        }

        if (is_float($value) && floor($value) === $value) {
            return $value > 0 ? (string) (int) $value : null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || $value === '0') {
                return null;
            }

            return ctype_digit($value) ? (string) (int) $value : mb_substr($value, 0, 191);
        }

        return null;
    }

    private static function money(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private static function string(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function imageUrl(mixed $value): ?string
    {
        $url = self::string($value);

        if ($url === null || strlen($url) > 2048 || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function parseAbandonedAt(array $payload): CarbonInterface
    {
        $iso = self::string($payload['abandoned_at'] ?? null);

        if ($iso !== null) {
            try {
                return self::local(Carbon::parse($iso));
            } catch (\Throwable) {
                // fall through to the legacy unix timestamp
            }
        }

        return self::timestamp($payload['timestamp'] ?? null) ?? now();
    }

    private static function timestamp(mixed $value): ?CarbonInterface
    {
        if (! is_numeric($value) || (int) $value <= 0) {
            return null;
        }

        return self::local(Carbon::createFromTimestampUTC((int) $value));
    }

    /**
     * Datetime columns store wall-clock time in the app timezone, so a UTC
     * instant must be converted before it is saved or it shifts by the offset.
     */
    private static function local(CarbonInterface $moment): CarbonInterface
    {
        return $moment->setTimezone((string) config('app.timezone', 'UTC'));
    }

    private static function channelLabel(string $channel): string
    {
        return match (strtolower($channel)) {
            'email' => 'email',
            'sms' => 'SMS',
            'whatsapp' => 'WhatsApp',
            default => $channel,
        };
    }
}
