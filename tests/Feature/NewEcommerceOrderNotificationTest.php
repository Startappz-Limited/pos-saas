<?php

use App\Jobs\ProcessOrderWebhookJob;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\Messages\WhatsAppTemplateMessage;
use App\Notifications\NewEcommerceOrderNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->manager = User::factory()->create(['phone' => '+254700000000']);
    $this->shop = Shop::factory()->create(['manager_id' => $this->manager->id]);
    $this->order = EcommerceOrder::factory()
        ->woocommerce()
        ->pending()
        ->create([
            'shop_id' => $this->shop->id,
            'customer_name' => 'John Doe',
            'total' => 5000.00,
            'currency' => 'KES',
        ]);

    EcommerceOrderItem::create([
        'ecommerce_order_id' => $this->order->id,
        'name' => 'Protein Powder',
        'quantity' => 2,
        'unit_price' => 2000,
        'subtotal' => 4000,
        'total' => 4000,
    ]);
    EcommerceOrderItem::create([
        'ecommerce_order_id' => $this->order->id,
        'name' => 'Gym Gloves',
        'quantity' => 1,
        'unit_price' => 1000,
        'subtotal' => 1000,
        'total' => 1000,
    ]);
});

it('sends notification via mail channel', function () {
    Notification::fake();

    $this->manager->notify(new NewEcommerceOrderNotification($this->order));

    Notification::assertSentTo($this->manager, NewEcommerceOrderNotification::class, function ($notification, $channels) {
        return in_array('mail', $channels);
    });
});

it('includes sms channel when SMS is configured and WhatsApp is not', function () {
    putenv('SMS_API_URL=https://sms.example.com/api');
    putenv('SMS_API_TOKEN=test-token');
    config(['services.whatsapp.api_url' => null]);
    config(['services.whatsapp.api_token' => null]);

    $notification = new NewEcommerceOrderNotification($this->order);
    $channels = $notification->via($this->manager);

    expect($channels)->toContain('mail')
        ->and($channels)->toContain(SmsChannel::class)
        ->and($channels)->not->toContain(WhatsAppChannel::class);

    putenv('SMS_API_URL');
    putenv('SMS_API_TOKEN');
});

it('excludes sms channel when WhatsApp is configured', function () {
    putenv('SMS_API_URL=https://sms.example.com/api');
    putenv('SMS_API_TOKEN=test-token');
    config(['services.whatsapp.api_url' => 'https://wa.example.com/api']);
    config(['services.whatsapp.api_token' => 'test-token']);

    $notification = new NewEcommerceOrderNotification($this->order);
    $channels = $notification->via($this->manager);

    expect($channels)->toContain(WhatsAppChannel::class)
        ->and($channels)->not->toContain(SmsChannel::class);

    putenv('SMS_API_URL');
    putenv('SMS_API_TOKEN');
});

it('excludes sms channel when SMS env vars are not configured', function () {
    putenv('SMS_API_URL');
    putenv('SMS_API_TOKEN');

    $notification = new NewEcommerceOrderNotification($this->order);
    $channels = $notification->via($this->manager);

    expect($channels)->toContain('mail')
        ->and($channels)->not->toContain(SmsChannel::class);
});

it('includes whatsapp channel when configured', function () {
    config(['services.whatsapp.api_url' => 'https://wa.example.com/api']);
    config(['services.whatsapp.api_token' => 'test-token']);

    $notification = new NewEcommerceOrderNotification($this->order);
    $channels = $notification->via($this->manager);

    expect($channels)->toContain(WhatsAppChannel::class);
});

it('excludes whatsapp channel when not configured', function () {
    config(['services.whatsapp.api_url' => null]);
    config(['services.whatsapp.api_token' => null]);

    $notification = new NewEcommerceOrderNotification($this->order);
    $channels = $notification->via($this->manager);

    expect($channels)->not->toContain(WhatsAppChannel::class);
});

it('generates correct mail content', function () {
    $notification = new NewEcommerceOrderNotification($this->order);
    $mail = $notification->toMail($this->manager);

    expect($mail->subject)->toContain($this->order->order_number)
        ->and($mail->subject)->toContain($this->shop->name)
        ->and($mail->greeting)->toBe("New {$this->shop->name} Website Order!")
        ->and($mail->introLines)->toContain('**Customer:** John Doe')
        ->and($mail->introLines)->toContain('**Total:** KES 5,000.00')
        ->and($mail->introLines)->toContain('**Items (2):**')
        ->and($mail->introLines)->toContain('- Protein Powder x2')
        ->and($mail->introLines)->toContain('- Gym Gloves x1');
});

it('generates correct sms content', function () {
    $notification = new NewEcommerceOrderNotification($this->order);
    $sms = $notification->toSms($this->manager);

    expect($sms)->toContain($this->order->order_number)
        ->and($sms)->toContain('John Doe')
        ->and($sms)->toContain('KES 5,000.00')
        ->and($sms)->toContain($this->shop->name);
});

it('generates correct whatsapp template message', function () {
    $notification = new NewEcommerceOrderNotification($this->order);
    $wa = $notification->toWhatsApp($this->manager);

    expect($wa)->toBeInstanceOf(WhatsAppTemplateMessage::class)
        ->and($wa->templateName)->toBe('new_order_notification_utility')
        ->and($wa->languageCode)->toBe('en')
        ->and($wa->components)->toHaveCount(2);

    // Body parameters
    $body = $wa->components[0];
    expect($body['type'])->toBe('body')
        ->and($body['parameters'])->toHaveCount(6)
        ->and($body['parameters'][0]['text'])->toBe($this->shop->name)
        ->and($body['parameters'][1]['text'])->toBe($this->order->order_number)
        ->and($body['parameters'][2]['text'])->toBe('John Doe')
        ->and($body['parameters'][3]['text'])->toBe('KES 5,000.00')
        ->and($body['parameters'][4]['text'])->toBe('2')
        ->and($body['parameters'][5]['text'])->toContain('Protein Powder x2')
        ->and($body['parameters'][5]['text'])->toContain('Gym Gloves x1');

    // Button URL suffix (Meta template has static prefix https://pos.startappz.co.ke/orders/{{1}})
    $button = $wa->components[1];
    expect($button['type'])->toBe('button')
        ->and($button['sub_type'])->toBe('url')
        ->and($button['index'])->toBe(0)
        ->and($button['parameters'][0]['text'])->not->toStartWith('http')
        ->and($button['parameters'][0]['text'])->toContain($this->order->uuid)
        ->and($button['parameters'][0]['text'])->toContain('invoice?signature=');
});

it('includes order data in array representation', function () {
    $notification = new NewEcommerceOrderNotification($this->order);
    $data = $notification->toArray($this->manager);

    expect($data)->toHaveKeys(['order_id', 'order_number', 'platform', 'total', 'customer_name'])
        ->and($data['order_id'])->toBe($this->order->id)
        ->and($data['platform'])->toBe('woocommerce')
        ->and($data['customer_name'])->toBe('John Doe');
});

it('queues on order-notifications queue', function () {
    $notification = new NewEcommerceOrderNotification($this->order);

    expect($notification->queue)->toBe('order-notifications');
});

// --- Recipient fallback tests ---

it('notifies shop manager when manager is defined', function () {
    Notification::fake();

    $job = new ProcessOrderWebhookJob(
        shop: $this->shop,
        platform: 'woocommerce',
        topic: 'order.created',
        payload: []
    );

    $reflection = new ReflectionMethod($job, 'notifyShopUsers');
    $reflection->invoke($job, $this->order);

    Notification::assertSentTo($this->manager, NewEcommerceOrderNotification::class);
});

it('falls back to shop contact info when no manager is defined', function () {
    $logMessages = [];
    Log::listen(function ($event) use (&$logMessages) {
        $logMessages[] = ['level' => $event->level, 'message' => $event->message, 'context' => $event->context ?? []];
    });

    $shop = Shop::factory()->create([
        'manager_id' => null,
        'email' => 'shop@example.com',
        'phone' => '+254711000000',
    ]);

    $order = EcommerceOrder::factory()
        ->woocommerce()
        ->pending()
        ->create(['shop_id' => $shop->id, 'customer_name' => 'Jane Doe', 'total' => 1000]);

    $job = new ProcessOrderWebhookJob(
        shop: $shop,
        platform: 'woocommerce',
        topic: 'order.created',
        payload: []
    );

    $reflection = new ReflectionMethod($job, 'notifyShopUsers');
    $reflection->invoke($job, $order);

    $fallbackLog = collect($logMessages)->first(fn($log) => str_contains($log['message'], 'Order notification sent to shop contact info'));

    expect($fallbackLog)->not->toBeNull()
        ->and($fallbackLog['level'])->toBe('info');
});

it('routes whatsapp in fallback when shop has phone', function () {
    Notification::fake();

    $shop = Shop::factory()->create([
        'manager_id' => null,
        'email' => 'shop@example.com',
        'phone' => '+254711000000',
    ]);

    $order = EcommerceOrder::factory()
        ->woocommerce()
        ->pending()
        ->create(['shop_id' => $shop->id, 'customer_name' => 'Jane Doe', 'total' => 1000]);

    $job = new ProcessOrderWebhookJob(
        shop: $shop,
        platform: 'woocommerce',
        topic: 'order.created',
        payload: []
    );

    $reflection = new ReflectionMethod($job, 'notifyShopUsers');
    $reflection->invoke($job, $order);

    Notification::assertSentOnDemand(
        NewEcommerceOrderNotification::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routeNotificationFor('whatsapp') === '+254711000000'
                && $notifiable->routeNotificationFor('sms') === '+254711000000';
        }
    );
});

it('sends whatsapp with correct meta cloud api template format', function () {
    config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
    config(['services.whatsapp.api_token' => 'test-token']);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.123']]], 200),
    ]);

    $channel = app(WhatsAppChannel::class);
    $notification = new NewEcommerceOrderNotification($this->order);

    $notifiable = Notification::route('whatsapp', '+254700000000');
    $channel->send($notifiable, $notification);

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $body['messaging_product'] === 'whatsapp'
            && $body['to'] === '+254700000000'
            && $body['type'] === 'template'
            && $body['template']['name'] === 'new_order_notification_utility'
            && $body['template']['language']['code'] === 'en'
            && ! empty($body['template']['components']);
    });
});

it('falls back to SMS when WhatsApp fails', function () {
    config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
    config(['services.whatsapp.api_token' => 'test-token']);
    putenv('SMS_API_URL=https://sms.example.com/api');
    putenv('SMS_API_TOKEN=test-token');
    putenv('SMS_SENDER_ID=TEST');

    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'template not found']], 404),
        'sms.example.com/*' => Http::response(['success' => true], 200),
    ]);

    $logMessages = [];
    Log::listen(function ($event) use (&$logMessages) {
        $logMessages[] = ['level' => $event->level, 'message' => $event->message];
    });

    $channel = app(WhatsAppChannel::class);
    $notification = new NewEcommerceOrderNotification($this->order);
    $notifiable = Notification::route('whatsapp', '+254700000000')
        ->route('sms', '+254700000000');

    $channel->send($notifiable, $notification);

    $fallbackLog = collect($logMessages)->first(fn($log) => str_contains($log['message'], 'falling back to SMS'));

    expect($fallbackLog)->not->toBeNull();

    putenv('SMS_API_URL');
    putenv('SMS_API_TOKEN');
    putenv('SMS_SENDER_ID');
});

it('does not send SMS when WhatsApp succeeds', function () {
    config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
    config(['services.whatsapp.api_token' => 'test-token']);
    putenv('SMS_API_URL=https://sms.example.com/api');
    putenv('SMS_API_TOKEN=test-token');

    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.123']]], 200),
    ]);

    $logMessages = [];
    Log::listen(function ($event) use (&$logMessages) {
        $logMessages[] = ['level' => $event->level, 'message' => $event->message];
    });

    $channel = app(WhatsAppChannel::class);
    $notification = new NewEcommerceOrderNotification($this->order);
    $notifiable = Notification::route('whatsapp', '+254700000000');

    $channel->send($notifiable, $notification);

    $fallbackLog = collect($logMessages)->first(fn($log) => str_contains($log['message'], 'falling back to SMS'));

    expect($fallbackLog)->toBeNull();

    Http::assertSentCount(1);

    putenv('SMS_API_URL');
    putenv('SMS_API_TOKEN');
});

it('logs warning when no manager and no shop contact info', function () {
    $logMessages = [];
    Log::listen(function ($event) use (&$logMessages) {
        $logMessages[] = ['level' => $event->level, 'message' => $event->message];
    });

    $shop = Shop::factory()->create([
        'manager_id' => null,
        'email' => null,
        'phone' => null,
    ]);

    $order = EcommerceOrder::factory()
        ->woocommerce()
        ->pending()
        ->create(['shop_id' => $shop->id, 'customer_name' => 'Test', 'total' => 500]);

    $job = new ProcessOrderWebhookJob(
        shop: $shop,
        platform: 'woocommerce',
        topic: 'order.created',
        payload: []
    );

    $reflection = new ReflectionMethod($job, 'notifyShopUsers');
    $reflection->invoke($job, $order);

    $warningLog = collect($logMessages)->first(fn($log) => str_contains($log['message'], 'No recipients for order notification'));

    expect($warningLog)->not->toBeNull()
        ->and($warningLog['level'])->toBe('warning');
});

it('serves invoice pdf via signed url', function () {
    $url = URL::signedRoute('orders.invoice-pdf', $this->order);

    $response = $this->get($url);

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('pdf');
});

it('rejects invoice pdf without valid signature', function () {
    $this->get("/orders/{$this->order->uuid}/invoice")
        ->assertForbidden();
});
