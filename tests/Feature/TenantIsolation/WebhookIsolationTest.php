<?php

use App\Jobs\ProcessOrderWebhookJob;
use App\Models\Shop;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TwoBusinesses;

/**
 * Webhooks stay within one business (Phase 2). A webhook names its shop and
 * is signed with that shop's own secret, so another business's secret (or a
 * valid delivery replayed at another business's shop) is refused, and the
 * order job that follows is for that shop only. That a queued job acts for
 * its business is covered by Tenancy/BackgroundWorkTest; M-Pesa webhooks get
 * their rows when Phase 6 builds them.
 */
beforeEach(function () {
    Queue::fake();
    $this->fixture = TwoBusinesses::create();
    $this->shopA = withWooSecret($this->fixture->a->business, $this->fixture->a->shops[0], 'secret-of-a');
    $this->shopB = withWooSecret($this->fixture->b->business, $this->fixture->b->shops[0], 'secret-of-b');
    app(TenantContext::class)->forget();
});

function withWooSecret($business, Shop $shop, string $secret): Shop
{
    return app(TenantContext::class)->run($business, function () use ($shop, $secret): Shop {
        $shop->setIntegrationConfig('woocommerce', ['enabled' => true, 'webhook_secret' => Crypt::encrypt($secret)]);
        $shop->save();

        return $shop->fresh();
    });
}

function deliverWoo(Shop $shop, string $payload, string $secret)
{
    return test()->call('POST', "/api/webhooks/woocommerce/{$shop->uuid}", [], [], [], [
        'HTTP_X_WC_WEBHOOK_TOPIC' => 'order.created',
        'HTTP_X_WC_WEBHOOK_SIGNATURE' => base64_encode(hash_hmac('sha256', $payload, $secret, true)),
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
}

it('queues the order for the shop the webhook names, signed with its own secret', function () {
    $payload = json_encode(['id' => 501, 'status' => 'processing']);

    deliverWoo($this->shopA, $payload, 'secret-of-a')->assertSuccessful();

    Queue::assertPushed(ProcessOrderWebhookJob::class, 1);
    Queue::assertPushed(ProcessOrderWebhookJob::class, fn (ProcessOrderWebhookJob $job) => $job->shop->is($this->shopA)
        && $job->shop->business_id === $this->fixture->a->business->id);
});

it('refuses another business\'s secret, and a delivery replayed at another business\'s shop', function () {
    $payload = json_encode(['id' => 502, 'status' => 'processing']);

    // B's shop, signed with A's secret.
    deliverWoo($this->shopB, $payload, 'secret-of-a')->assertStatus(401);

    Queue::assertNotPushed(ProcessOrderWebhookJob::class);
});
