<?php

use App\Actions\Baileys\NotifyCustomerOfEcommerceOrder;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\EcommerceOrder;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('wa-gateway.url', 'https://gateway.test');
    config()->set('wa-gateway.token', 'test-token');
    config()->set('wa-gateway.webhook.secret', 'shh');
    config()->set('baileys.notify_on_ecommerce_order', true);
});

it('includes the order total and currency in the customer notification', function () {
    Http::fake([
        'gateway.test/sessions/*/messages/text' => Http::response(['wa_message_id' => 'WAID-1'], 200),
    ]);

    $shop = Shop::factory()->create();
    BaileysSession::factory()->for($shop)->connected()->create();

    $order = EcommerceOrder::factory()->for($shop)->create([
        'order_number' => '#6482',
        'currency' => 'KES',
        'total' => 1234.50,
        'customer_phone' => '254712345678',
        // No customer_id here: ecommerce_orders has no such column — the platform
        // customer is captured by customer_name/_email/_phone.
    ]);

    $message = app(NotifyCustomerOfEcommerceOrder::class)->execute($order);

    expect($message)->toBeInstanceOf(BaileysMessage::class)
        ->and($message->content)->toContain('#6482')
        ->and($message->content)->toContain('KES 1,234.50')
        ->and($message->content)->not->toContain('Total: 0.00');
});
