<?php

use App\Actions\Baileys\NotifyCustomerOfEcommerceOrder;
use App\Contracts\MpesaGateway;
use App\Models\BaileysSession;
use App\Models\BusinessIntegration;
use App\Models\EcommerceOrder;
use App\Models\Shop;
use App\Services\Mpesa\FakeMpesaGateway;
use App\Services\Mpesa\NullMpesaGateway;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\TwoBusinesses;

/**
 * A shop's own M-Pesa Till or Paybill (stage 3, open question 2; decision
 * 4.3): accepted only after a successful test query with the shop's own
 * account details, one shop per number on the platform, details stored
 * encrypted.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->b = $this->fixture->b;
    $this->shop = $this->a->shops[0];
    $this->gateway = new FakeMpesaGateway;
    app()->instance(MpesaGateway::class, $this->gateway);
});

function mpesaForm(array $overrides = []): array
{
    return ['shortcode' => '577 42 79', 'type' => 'till', 'consumer_key' => 'ck_live_123', 'consumer_secret' => 'cs_live_456', ...$overrides];
}

it('saves a Till after a successful test query, storing the details encrypted', function () {
    $this->actingAs($this->a->owner)->put(route('shops.mpesa.update', $this->shop), mpesaForm())
        ->assertRedirect(route('shops.mpesa.edit', $this->shop))->assertSessionHas('success');

    $shop = $this->shop->fresh();
    expect($shop->mpesa_shortcode)->toBe('5774279')
        ->and($shop->mpesa_shortcode_type)->toBe('till')
        ->and($shop->mpesa_verified_at)->not->toBeNull()
        ->and($this->gateway->checks)->toHaveCount(1)
        ->and($this->gateway->checks[0]['credentials']['consumer_secret'])->toBe('cs_live_456');

    $raw = DB::table('business_integrations')->where('shop_id', $shop->id)->value('credentials');
    expect($raw)->not->toContain('cs_live_456')->not->toContain('ck_live_123');

    $integration = app(TenantContext::class)->run($this->a->business, fn () => BusinessIntegration::forShop('mpesa', $shop->id));
    expect($integration->credentials['consumer_key'])->toBe('ck_live_123');
});

it('saves nothing when the test query fails', function () {
    $this->gateway->failWith('Invalid credentials');

    $this->actingAs($this->a->owner)->put(route('shops.mpesa.update', $this->shop), mpesaForm())->assertSessionHasErrors('shortcode');

    expect($this->shop->fresh()->mpesa_shortcode)->toBeNull()
        ->and(DB::table('business_integrations')->count())->toBe(0);
});

it('accepts nothing while the M-Pesa package is not connected', function () {
    app()->bind(MpesaGateway::class, NullMpesaGateway::class);

    $this->actingAs($this->a->owner)->put(route('shops.mpesa.update', $this->shop), mpesaForm())->assertSessionHasErrors('shortcode');

    expect($this->shop->fresh()->mpesa_shortcode)->toBeNull();
});

it('lets a number belong to one shop on the platform only', function () {
    $this->actingAs($this->b->owner)->put(route('shops.mpesa.update', $this->b->shops[0]), mpesaForm(['shortcode' => '123456']))->assertSessionHas('success');
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    $this->actingAs($this->a->owner)->put(route('shops.mpesa.update', $this->shop), mpesaForm(['shortcode' => '123456']))
        ->assertSessionHasErrors('shortcode');

    expect($this->shop->fresh()->mpesa_shortcode)->toBeNull();
});

it('frees the number when the shop is deleted', function () {
    $this->actingAs($this->a->owner)->put(route('shops.mpesa.update', $this->shop), mpesaForm(['shortcode' => '654321']));
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    $this->actingAs($this->a->owner)->delete(route('shops.destroy', $this->shop));
    app('auth')->forgetGuards();
    app(TenantContext::class)->forget();

    $this->actingAs($this->b->owner)->put(route('shops.mpesa.update', $this->b->shops[0]), mpesaForm(['shortcode' => '654321']))->assertSessionHas('success');
});

it('keeps the stored details when the form leaves them blank for the same number', function () {
    $this->actingAs($this->a->owner)->put(route('shops.mpesa.update', $this->shop), mpesaForm());
    $this->actingAs($this->a->owner)->put(route('shops.mpesa.update', $this->shop), mpesaForm(['consumer_key' => '', 'consumer_secret' => '']))->assertSessionHas('success');

    expect(collect($this->gateway->checks)->last()['credentials']['consumer_secret'])->toBe('cs_live_456');
});

it('keeps another business\'s shop out of reach', function () {
    $this->actingAs($this->a->owner)->put(route('shops.mpesa.update', $this->b->shops[0]), mpesaForm())->assertNotFound();
});

it('tells the customer the shop\'s own Till, and no other number', function () {
    config()->set('wa-gateway.url', 'https://gateway.test');
    config()->set('wa-gateway.token', 'test-token');
    config()->set('wa-gateway.webhook.secret', 'shh');
    config()->set('baileys.notify_on_ecommerce_order', true);
    Http::fake(['gateway.test/sessions/*/messages/text' => Http::response(['wa_message_id' => 'WAID-1'], 200)]);

    $this->actingAs($this->a->owner)->put(route('shops.mpesa.update', $this->shop), mpesaForm(['shortcode' => '888999']));
    app(TenantContext::class)->forget();

    $message = app(TenantContext::class)->run($this->a->business, function (): mixed {
        $shop = Shop::query()->findOrFail($this->shop->id);
        BaileysSession::factory()->for($shop)->connected()->create();
        $order = EcommerceOrder::factory()->for($shop)->create(['currency' => 'KES', 'total' => 100, 'customer_phone' => '254712345678']);

        return app(NotifyCustomerOfEcommerceOrder::class)->execute($order);
    });

    expect($message->content)->toContain('M-Pesa Till: *888999*')->not->toContain('577 42 79');
});
