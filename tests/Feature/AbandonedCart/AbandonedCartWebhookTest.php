<?php

use App\Enums\AbandonedCartStatus;
use App\Enums\AlertType;
use App\Jobs\ProcessAbandonedCartWebhookJob;
use App\Jobs\ProcessOrderWebhookJob;
use App\Models\AbandonedCart;
use App\Models\Customer;
use App\Models\EcommerceOrder;
use App\Models\Product;
use App\Models\ProductEcommerceSync;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\NewAbandonedCartNotification;
use App\Services\Integration\AbandonedCartSyncService;
use App\Services\Integration\ShopifyOrderSyncService;
use App\Services\Integration\WooCommerceOrderSyncService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

require_once __DIR__.'/helpers.php';

/**
 * The inbound half: Abandoned Cart Recovery plugin webhooks → POS carts.
 */
beforeEach(function () {
    Notification::fake();
    $this->shop = acrShop();
});

function runAcrJob(Shop $shop, string $topic, array $payload): void
{
    (new ProcessAbandonedCartWebhookJob($shop, $topic, $payload))->handle(app(AbandonedCartSyncService::class));
}

describe('the signed webhook path', function () {
    it('creates a cart from a signed cut-off delivered to the real endpoint', function () {
        postAcrWebhook($this->shop, 'acr_cart.cutoff', acrCutoffPayload())->assertOk();

        $cart = AbandonedCart::sole();

        expect($cart->shop_id)->toBe($this->shop->id)
            ->and($cart->platform_cart_id)->toBe('501')
            ->and($cart->status)->toBe(AbandonedCartStatus::Abandoned)
            ->and($cart->items)->toHaveCount(1);
    });

    it('queues the cart job on the ecommerce queue and never the order job', function () {
        Queue::fake();

        postAcrWebhook($this->shop, 'acr_cart.cutoff', acrCutoffPayload())->assertOk();

        Queue::assertPushedOn('ecommerce', ProcessAbandonedCartWebhookJob::class);
        Queue::assertNotPushed(ProcessOrderWebhookJob::class);
    });

    it('rejects a forged cut-off and stores nothing', function () {
        postAcrWebhook($this->shop, 'acr_cart.cutoff', acrCutoffPayload(), 'not-the-secret')->assertStatus(401);

        expect(AbandonedCart::count())->toBe(0);
    });
});

describe('acr_cart.cutoff', function () {
    it('maps the payload onto the cart', function () {
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());

        $cart = AbandonedCart::sole();
        $item = $cart->items->sole();

        expect($cart->customer_name)->toBe('Jane Wanjiku')
            ->and($cart->customer_email)->toBe('jane@example.com')
            ->and($cart->customer_phone)->toBe('0712 345 678')
            ->and($cart->phone_normalized)->toBe('254712345678')
            ->and($cart->user_type)->toBe('GUEST')
            ->and($cart->capture_source)->toBe('checkout')
            ->and($cart->platform_status)->toBe('abandoned')
            ->and($cart->currency)->toBe('KES')
            ->and($cart->subtotal)->toBe('2000.00')
            ->and($cart->tax_total)->toBe('320.00')
            ->and($cart->total)->toBe('2320.00')
            ->and($cart->abandoned_at->equalTo(Carbon::parse('2026-09-26T08:00:00Z')))->toBeTrue()
            ->and($cart->reminders_sent)->toBe(0)
            ->and($item->name)->toBe('Yoga Mat')
            ->and($item->platform_product_id)->toBe('11')
            ->and($item->platform_variation_id)->toBeNull()
            ->and($item->quantity)->toBe(2)
            ->and($item->unit_price)->toBe('1160.00')
            ->and($item->line_total)->toBe('2320.00')
            ->and($item->image_url)->toBe('https://shop.test/wp-content/uploads/mat.jpg');
    });

    it('stores the recovery link encrypted and keeps it out of platform_data and JSON', function () {
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());

        $cart = AbandonedCart::sole();
        $raw = DB::table('abandoned_carts')->value('checkout_link');

        expect($cart->checkout_link)->toBe('https://shop.test/?acr_recover=501&token=SECRET-TOKEN')
            ->and($raw)->not->toContain('SECRET-TOKEN')
            ->and($cart->platform_data)->not->toHaveKey('checkout_link')
            ->and(json_encode($cart->toArray()))->not->toContain('SECRET-TOKEN')
            ->and(DB::table('abandoned_carts')->value('platform_data'))->not->toContain('SECRET-TOKEN');
    });

    it('is idempotent: a replayed delivery changes nothing', function () {
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());

        $cart = AbandonedCart::sole();

        expect($cart->items()->count())->toBe(1)
            ->and($cart->alerts()->where('type', AlertType::CART_ACTIVITY->value)->count())->toBe(1);
    });

    it('replaces the lines when the cart changed', function () {
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());

        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload([
            'product_details' => [
                ['product_id' => 12, 'variation_id' => 0, 'product_name' => 'Kettlebell', 'quantity' => 1, 'price' => '3000.00', 'line_subtotal' => '3000', 'total' => '3000', 'total_tax' => '0'],
                ['product_id' => 13, 'variation_id' => 0, 'product_name' => 'Chalk', 'quantity' => 3, 'price' => '100.00', 'line_subtotal' => '300', 'total' => '300', 'total_tax' => '0'],
            ],
            'cart_total' => '3300.00',
        ]));

        $cart = AbandonedCart::sole();

        expect($cart->items()->pluck('name')->all())->toBe(['Kettlebell', 'Chalk'])
            ->and($cart->total)->toBe('3300.00');
    });

    it('keeps a status staff already set', function (AbandonedCartStatus $status) {
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());
        AbandonedCart::sole()->update(['status' => $status]);

        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload(['abandoned_at' => '2026-09-27T08:00:00Z']));

        expect(AbandonedCart::sole()->status)->toBe($status);
    })->with([
        'contacted' => AbandonedCartStatus::Contacted,
        'converted' => AbandonedCartStatus::Converted,
        'lost' => AbandonedCartStatus::Lost,
    ]);

    it('tolerates string ids and a payload without the newer fields', function () {
        // The shape the plugin sent before sku/quantity/price/image/cart_total… existed.
        runAcrJob($this->shop, 'acr_cart.cutoff', [
            'webhook_resource' => 'acr_cart',
            'webhook_action' => 'cutoff',
            'id' => '0042',
            'timestamp' => '1790000000',
            'billing_first_name' => 'Old',
            'billing_last_name' => 'Payload',
            'email_id' => 'old@example.com',
            'phone' => '',
            'currency' => 'KES',
            'total' => '580.00',
            'tax_total' => '80.00',
            'product_details' => [
                ['product_id' => '77', 'variation_id' => '0', 'product_name' => 'Bottle', 'line_subtotal' => '500.00', 'total' => '500.00', 'total_tax' => '80.00'],
            ],
        ]);

        $cart = AbandonedCart::sole();
        $item = $cart->items->sole();

        expect($cart->platform_cart_id)->toBe('42')
            ->and($cart->customer_name)->toBe('Old Payload')
            ->and($cart->customer_phone)->toBeNull()
            ->and($cart->total)->toBe('580.00')
            ->and($cart->abandoned_at->getTimestamp())->toBe(1790000000)
            ->and($item->platform_product_id)->toBe('77')
            ->and($item->quantity)->toBe(1)
            ->and($item->unit_price)->toBe('580.00')
            ->and($item->sku)->toBeNull()
            ->and($item->image_url)->toBeNull();

        // "42" and 42 are the same cart.
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload(['id' => 42]));
        expect(AbandonedCart::count())->toBe(1);
    });

    it('ignores a null abandoned_at and falls back to the timestamp', function () {
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload(['abandoned_at' => null]));

        expect(AbandonedCart::sole()->abandoned_at->getTimestamp())->toBe(1790000000);
    });

    it('ignores a cart the website reports deleted', function () {
        runAcrJob($this->shop, 'acr_cart.cutoff', ['id' => 501, 'deleted' => true, 'webhook_resource' => 'acr_cart']);

        expect(AbandonedCart::count())->toBe(0);
    });

    it('keeps carts of different shops apart even with the same plugin cart id', function () {
        $other = acrShop();

        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());
        runAcrJob($other, 'acr_cart.cutoff', acrCutoffPayload());

        expect(AbandonedCart::count())->toBe(2)
            ->and(AbandonedCart::where('shop_id', $other->id)->count())->toBe(1);
    });

    it('notifies shop managers of a NEW cart only', function () {
        $manager = User::factory()->create();
        $this->shop->update(['manager_id' => $manager->id]);

        runAcrJob($this->shop->fresh(), 'acr_cart.cutoff', acrCutoffPayload());
        runAcrJob($this->shop->fresh(), 'acr_cart.cutoff', acrCutoffPayload());

        Notification::assertSentToTimes($manager, NewAbandonedCartNotification::class, 1);
    });

    it('never puts the recovery link in the staff notification', function () {
        $manager = User::factory()->create();
        $this->shop->update(['manager_id' => $manager->id]);

        runAcrJob($this->shop->fresh(), 'acr_cart.cutoff', acrCutoffPayload());

        Notification::assertSentTo($manager, NewAbandonedCartNotification::class, function ($notification) use ($manager) {
            $mail = $notification->toMail($manager)->render();

            return ! str_contains((string) $mail, 'SECRET-TOKEN')
                && ! str_contains($notification->toSms($manager), 'SECRET-TOKEN');
        });
    });
});

describe('product matching', function () {
    it('matches a line by the product sync record first', function () {
        $product = Product::factory()->create(['sku' => 'LOCAL-1']);
        ProductEcommerceSync::create([
            'product_id' => $product->id,
            'shop_id' => $this->shop->id,
            'platform' => 'woocommerce',
            'platform_product_id' => '11',
        ]);

        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());

        expect(AbandonedCart::sole()->items->sole()->product_id)->toBe($product->id);
    });

    it('falls back to the SKU', function () {
        $product = Product::factory()->create(['sku' => 'MAT-BLUE']);
        $payload = acrCutoffPayload();
        $payload['product_details'][0]['sku'] = 'MAT-BLUE';

        runAcrJob($this->shop, 'acr_cart.cutoff', $payload);

        expect(AbandonedCart::sole()->items->sole()->product_id)->toBe($product->id);
    });

    it('resolves a variation SKU to the POS variation', function () {
        $product = Product::factory()->create(['sku' => 'TEE', 'has_variations' => true]);
        $variation = ProductVariation::factory()->create(['product_id' => $product->id, 'sku' => 'TEE-L']);
        $payload = acrCutoffPayload();
        $payload['product_details'][0]['sku'] = 'TEE-L';
        $payload['product_details'][0]['variation_id'] = 99;

        runAcrJob($this->shop, 'acr_cart.cutoff', $payload);

        $item = AbandonedCart::sole()->items->sole();

        expect($item->product_id)->toBe($product->id)
            ->and($item->variation_id)->toBe($variation->id)
            ->and($item->platform_variation_id)->toBe('99')
            ->and($item->isSellable())->toBeTrue();
    });

    it('leaves a line unmatched when nothing fits', function () {
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());

        $cart = AbandonedCart::sole();

        expect($cart->items->sole()->product_id)->toBeNull()
            ->and($cart->has_unmatched_items)->toBeTrue();
    });
});

describe('customer matching', function () {
    it('links the shop customer with the same phone, however it was typed', function () {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id, 'phone' => '+254712345678', 'email' => 'other@example.com']);

        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());

        expect(AbandonedCart::sole()->customer_id)->toBe($customer->id);
    });

    it('falls back to the email', function () {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id, 'phone' => '0799000000', 'email' => 'JANE@example.com']);

        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());

        expect(AbandonedCart::sole()->customer_id)->toBe($customer->id);
    });

    it('never matches another shop\'s customer and never creates one', function () {
        $otherShop = Shop::factory()->create();
        Customer::factory()->create(['shop_id' => $otherShop->id, 'phone' => '0712345678', 'email' => 'jane@example.com']);
        $before = Customer::count();

        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());

        expect(AbandonedCart::sole()->customer_id)->toBeNull()
            ->and(Customer::count())->toBe($before);
    });
});

describe('reminder and click activity', function () {
    beforeEach(function () {
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());
        $this->cart = AbandonedCart::sole();
    });

    it('records a reminder sent on each channel once', function (string $topic, string $channel) {
        $payload = acrCutoffPayload([
            'webhook_resource' => strtok($topic, '.'),
            'webhook_action' => 'sent',
            'sent_id' => 31,
            'reminder_type' => $channel,
            'template_name' => 'First nudge',
            'links_included' => ['checkout_link' => 'https://shop.test/?acr_recover=501&token=SECRET-TOKEN'],
        ]);
        unset($payload['checkout_link']);

        runAcrJob($this->shop, $topic, $payload);
        runAcrJob($this->shop, $topic, $payload); // retried delivery

        $cart = $this->cart->fresh();
        $entry = $cart->alerts()->where('data->event', $topic)->sole();

        expect($cart->reminders_sent)->toBe(1)
            ->and($cart->last_activity)->toBe("{$channel}_sent")
            ->and($entry->type)->toBe(AlertType::CART_ACTIVITY)
            ->and($entry->message)->toContain('First nudge')
            ->and(json_encode($entry->toArray()))->not->toContain('SECRET-TOKEN');
    })->with([
        ['acr_email.sent', 'email'],
        ['acr_sms.sent', 'sms'],
        ['acr_whatsapp.sent', 'whatsapp'],
    ]);

    it('counts two different sends separately', function () {
        runAcrJob($this->shop, 'acr_email.sent', ['id' => 501, 'sent_id' => 1, 'reminder_type' => 'email']);
        runAcrJob($this->shop, 'acr_email.sent', ['id' => '501', 'sent_id' => '2', 'reminder_type' => 'email']);

        expect($this->cart->fresh()->reminders_sent)->toBe(2);
    });

    it('records a link click once per click', function () {
        $payload = ['id' => 501, 'sent_id' => 31, 'reminder_type' => 'email', 'link_clicked' => 'checkout', 'time_clicked' => 1790000500];

        runAcrJob($this->shop, 'acr_link.clicked', $payload);
        runAcrJob($this->shop, 'acr_link.clicked', $payload);
        runAcrJob($this->shop, 'acr_link.clicked', array_merge($payload, ['time_clicked' => 1790000900]));

        $cart = $this->cart->fresh();

        expect($cart->alerts()->where('data->event', 'acr_link.clicked')->count())->toBe(2)
            ->and($cart->last_activity)->toBe('link_clicked')
            ->and($cart->reminders_sent)->toBe(0);
    });

    it('logs and ignores activity for a cart the POS never received', function () {
        runAcrJob($this->shop, 'acr_email.sent', ['id' => 999, 'sent_id' => 1]);
        runAcrJob($this->shop, 'acr_link.clicked', ['id' => 999, 'sent_id' => 1, 'time_clicked' => 1]);
        runAcrJob($this->shop, 'acr_cart.recovered', ['id' => 999, 'order_id' => 5]);

        expect(AbandonedCart::count())->toBe(1)
            ->and($this->cart->fresh()->reminders_sent)->toBe(0);
    });

    it('ignores the email-capture topics', function (string $topic) {
        runAcrJob($this->shop, $topic, acrCutoffPayload(['id' => 777, 'webhook_action' => substr($topic, 9)]));

        expect(AbandonedCart::count())->toBe(1);
    })->with(['acr_cart.atc', 'acr_cart.checkout', 'acr_cart.exit', 'acr_cart.form']);

    it('does not apply one shop\'s activity to another shop\'s cart', function () {
        $other = acrShop();

        runAcrJob($other, 'acr_email.sent', ['id' => 501, 'sent_id' => 1, 'reminder_type' => 'email']);

        expect($this->cart->fresh()->reminders_sent)->toBe(0);
    });
});

describe('acr_cart.recovered', function () {
    beforeEach(function () {
        runAcrJob($this->shop, 'acr_cart.cutoff', acrCutoffPayload());
        $this->cart = AbandonedCart::sole();
    });

    it('marks the cart recovered and links the website order when it exists', function () {
        $order = EcommerceOrder::factory()->for($this->shop)->woocommerce()->create(['platform_order_id' => '9001']);

        runAcrJob($this->shop, 'acr_cart.recovered', ['id' => 501, 'order_id' => 9001, 'total' => '2320.00']);

        $cart = $this->cart->fresh();

        expect($cart->status)->toBe(AbandonedCartStatus::Recovered)
            ->and($cart->recovered_at)->not->toBeNull()
            ->and($cart->platform_order_id)->toBe('9001')
            ->and($cart->ecommerce_order_id)->toBe($order->id)
            ->and($cart->can_be_converted)->toBeFalse();
    });

    it('is idempotent', function () {
        runAcrJob($this->shop, 'acr_cart.recovered', ['id' => 501, 'order_id' => 9001]);
        $firstRecoveredAt = $this->cart->fresh()->recovered_at;

        $this->travel(5)->minutes();
        runAcrJob($this->shop, 'acr_cart.recovered', ['id' => 501, 'order_id' => 9001]);

        $cart = $this->cart->fresh();

        expect($cart->recovered_at->equalTo($firstRecoveredAt))->toBeTrue()
            ->and($cart->alerts()->where('data->event', 'acr_cart.recovered')->count())->toBe(1);
    });

    it('does not reopen a cart the POS already converted, and flags a possible double sale', function () {
        $this->cart->update(['status' => AbandonedCartStatus::Converted]);

        runAcrJob($this->shop, 'acr_cart.recovered', ['id' => 501, 'order_id' => 9001]);

        $cart = $this->cart->fresh();
        $entry = $cart->alerts()->where('data->event', 'acr_cart.recovered')->sole();

        expect($cart->status)->toBe(AbandonedCartStatus::Converted)
            ->and($entry->severity->value)->toBe('high');
    });

    it('links the cart when the website order arrives after the recovery', function () {
        runAcrJob($this->shop, 'acr_cart.recovered', ['id' => 501, 'order_id' => 9001]);
        expect($this->cart->fresh()->ecommerce_order_id)->toBeNull();

        (new ProcessOrderWebhookJob($this->shop, 'woocommerce', 'order.created', [
            'id' => 9001,
            'number' => '9001',
            'status' => 'processing',
            'total' => '2320.00',
            'billing' => ['first_name' => 'Jane', 'phone' => ''],
            'line_items' => [],
        ]))->handle(
            app(WooCommerceOrderSyncService::class),
            app(ShopifyOrderSyncService::class),
        );

        expect($this->cart->fresh()->ecommerce_order_id)->toBe(EcommerceOrder::where('platform_order_id', '9001')->value('id'));
    });
});
