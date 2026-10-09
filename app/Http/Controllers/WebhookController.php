<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAbandonedCartWebhookJob;
use App\Jobs\ProcessOrderWebhookJob;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle incoming WooCommerce webhook.
     */
    public function woocommerce(Request $request, Shop $shop): Response
    {
        // A closing business takes no new orders. 200 so the platform stops retrying.
        if ($shop->businessIsClosing()) {
            Log::info('Webhook ignored: the shop\'s business is closing', ['shop_id' => $shop->id]);

            return response('OK', 200);
        }

        $topic = $request->header('X-WC-Webhook-Topic');

        Log::info('WooCommerce webhook received', [
            'shop_id' => $shop->id,
            'topic' => $topic,
            'resource' => $request->header('X-WC-Webhook-Resource'),
            'event' => $request->header('X-WC-Webhook-Event'),
            'has_signature' => $request->hasHeader('X-WC-Webhook-Signature'),
        ]);

        // Accept requests without topic header as ping/test deliveries
        if (! $topic) {
            Log::info('WooCommerce webhook ping accepted (no topic header)', ['shop_id' => $shop->id]);

            return response('OK', 200);
        }

        // Skip ping/test deliveries
        if ($topic === 'action.woocommerce_webhook_test' || $request->header('X-WC-Webhook-Resource') === 'action') {
            Log::info('WooCommerce webhook ping/test accepted', ['shop_id' => $shop->id, 'topic' => $topic]);

            return response('OK', 200);
        }

        // Verification is mandatory: this endpoint is public, so an unverified
        // payload would let anyone who knows the shop UUID forge orders. Fails
        // closed when no secret is configured, matching shopify() below.
        $config = $shop->getIntegrationConfig('woocommerce');
        $webhookSecret = $config['webhook_secret'] ?? null;

        if (! $webhookSecret) {
            Log::warning('WooCommerce webhook rejected: no secret configured', [
                'shop_id' => $shop->id,
                'topic' => $topic,
            ]);

            return response('Webhook secret not configured', 400);
        }

        $signature = $request->header('X-WC-Webhook-Signature');

        if (! $signature) {
            Log::warning('WooCommerce webhook missing signature header', ['shop_id' => $shop->id, 'topic' => $topic]);

            return response('Missing signature header', 400);
        }

        try {
            $secret = Crypt::decrypt($webhookSecret);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt WooCommerce webhook secret', ['shop_id' => $shop->id]);

            return response('Configuration error', 500);
        }

        $computedSignature = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        if (! hash_equals($computedSignature, $signature)) {
            Log::warning('WooCommerce webhook signature verification failed', [
                'shop_id' => $shop->id,
                'topic' => $topic,
            ]);

            return response('Invalid signature', 401);
        }

        // Events from the Abandoned Cart Recovery plugin (resources acr_cart, acr_email, acr_sms,
        // acr_whatsapp, acr_link) describe carts, not orders. Their payload `id` is a CART id, so
        // they must never reach the order upsert, where they would create fake orders or overwrite
        // a real order that happens to share the number.
        if (self::isAbandonedCartEvent($topic, $request->header('X-WC-Webhook-Resource'))) {
            if (class_exists(ProcessAbandonedCartWebhookJob::class)) {
                ProcessAbandonedCartWebhookJob::dispatch($shop, $topic, $request->all());
            } else {
                Log::info('Abandoned-cart webhook ignored (handler not installed)', ['shop_id' => $shop->id, 'topic' => $topic]);
            }

            return response('OK', 200);
        }

        ProcessOrderWebhookJob::dispatch($shop, 'woocommerce', $topic, $request->all());

        return response('OK', 200);
    }

    /**
     * Whether a WooCommerce webhook comes from the Abandoned Cart Recovery plugin (topic/resource "acr_*").
     */
    public static function isAbandonedCartEvent(?string $topic, ?string $resource): bool
    {
        return str_starts_with((string) $resource, 'acr_') || str_starts_with((string) $topic, 'acr_');
    }

    /**
     * Handle incoming Shopify webhook.
     */
    public function shopify(Request $request, Shop $shop): Response
    {
        // A closing business takes no new orders. 200 so the platform stops retrying.
        if ($shop->businessIsClosing()) {
            Log::info('Webhook ignored: the shop\'s business is closing', ['shop_id' => $shop->id]);

            return response('OK', 200);
        }

        $signature = $request->header('X-Shopify-Hmac-Sha256');
        $topic = $request->header('X-Shopify-Topic');

        if (! $signature || ! $topic) {
            return response('Missing webhook headers', 400);
        }

        $config = $shop->getIntegrationConfig('shopify');
        $webhookSecret = $config['webhook_secret'] ?? null;

        if (! $webhookSecret) {
            Log::warning('Shopify webhook received but no secret configured', ['shop_id' => $shop->id]);

            return response('Webhook secret not configured', 400);
        }

        try {
            $secret = Crypt::decrypt($webhookSecret);
        } catch (\Exception $e) {
            Log::error('Failed to decrypt Shopify webhook secret', ['shop_id' => $shop->id]);

            return response('Configuration error', 500);
        }

        $computedSignature = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        if (! hash_equals($computedSignature, $signature)) {
            Log::warning('Shopify webhook signature verification failed', [
                'shop_id' => $shop->id,
                'topic' => $topic,
            ]);

            return response('Invalid signature', 401);
        }

        ProcessOrderWebhookJob::dispatch($shop, 'shopify', $topic, $request->all());

        return response('OK', 200);
    }
}
