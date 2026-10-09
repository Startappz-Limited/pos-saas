<?php

use App\Actions\SendWhatsAppToCustomer;
use App\Enums\WhatsAppMessageDirection;
use App\Enums\WhatsAppMessageStatus;
use App\Enums\WhatsAppMessageType;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\CustomerWhatsAppNotification;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->customer = Customer::factory()->forShop($this->shop)->create([
        'phone' => '+254712345678',
        'created_by' => $this->user->id,
    ]);
});

// --- WhatsAppService tests ---

describe('WhatsAppService', function () {
    it('sends a text message successfully', function () {
        config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
        config(['services.whatsapp.api_token' => 'test-token']);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.abc123']],
            ], 200),
        ]);

        $service = new WhatsAppService;
        $result = $service->sendTextMessage('+254712345678', 'Hello from test');

        expect($result['success'])->toBeTrue()
            ->and($result['message'])->toBe('Message sent successfully');

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['messaging_product'] === 'whatsapp'
                && $body['to'] === '+254712345678'
                && $body['type'] === 'text'
                && $body['text']['body'] === 'Hello from test';
        });
    });

    it('returns error when not configured', function () {
        config(['services.whatsapp.api_url' => null]);
        config(['services.whatsapp.api_token' => null]);

        $service = new WhatsAppService;
        $result = $service->sendTextMessage('+254712345678', 'Hello');

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toBe('WhatsApp service is not configured');
    });

    it('rejects invalid phone numbers', function () {
        config(['services.whatsapp.api_url' => 'https://api.example.com']);
        config(['services.whatsapp.api_token' => 'token']);

        $service = new WhatsAppService;

        expect($service->sendTextMessage('', 'test')['success'])->toBeFalse()
            ->and($service->sendTextMessage('0', 'test')['success'])->toBeFalse()
            ->and($service->sendTextMessage('abc', 'test')['success'])->toBeFalse();
    });

    it('sends a template message', function () {
        config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
        config(['services.whatsapp.api_token' => 'test-token']);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.tpl123']],
            ], 200),
        ]);

        $service = new WhatsAppService;
        $result = $service->sendTemplateMessage('+254712345678', 'hello_world', 'en');

        expect($result['success'])->toBeTrue();

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $body['type'] === 'template'
                && $body['template']['name'] === 'hello_world'
                && $body['template']['language']['code'] === 'en';
        });
    });

    it('handles API failure gracefully', function () {
        config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
        config(['services.whatsapp.api_token' => 'test-token']);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => 'Unauthorized'], 401),
        ]);

        $service = new WhatsAppService;
        $result = $service->sendTextMessage('+254712345678', 'Hello');

        expect($result['success'])->toBeFalse()
            ->and($result['message'])->toBe('Failed to send WhatsApp message');
    });

    it('reports configured status correctly', function () {
        config(['services.whatsapp.api_url' => 'https://api.example.com']);
        config(['services.whatsapp.api_token' => 'token']);

        expect(WhatsAppService::isConfigured())->toBeTrue();

        config(['services.whatsapp.api_url' => null]);

        expect(WhatsAppService::isConfigured())->toBeFalse();
    });
});

// --- WhatsAppMessage model tests ---

describe('WhatsAppMessage model', function () {
    it('creates a message with correct attributes', function () {
        $message = WhatsAppMessage::factory()->forShop($this->shop)->forCustomer($this->customer)->create([
            'sent_by' => $this->user->id,
            'content' => 'Test message',
        ]);

        expect($message->shop_id)->toBe($this->shop->id)
            ->and($message->customer_id)->toBe($this->customer->id)
            ->and($message->sent_by)->toBe($this->user->id)
            ->and($message->content)->toBe('Test message')
            ->and($message->uuid)->not->toBeNull()
            ->and($message->direction)->toBe(WhatsAppMessageDirection::Outbound)
            ->and($message->status)->toBe(WhatsAppMessageStatus::Pending);
    });

    it('marks message as sent', function () {
        $message = WhatsAppMessage::factory()->create();
        $message->markAsSent('wamid.abc123');

        expect($message->fresh()->status)->toBe(WhatsAppMessageStatus::Sent)
            ->and($message->fresh()->whatsapp_message_id)->toBe('wamid.abc123')
            ->and($message->fresh()->sent_at)->not->toBeNull();
    });

    it('marks message as failed', function () {
        $message = WhatsAppMessage::factory()->create();
        $message->markAsFailed('API timeout');

        expect($message->fresh()->status)->toBe(WhatsAppMessageStatus::Failed)
            ->and($message->fresh()->error_message)->toBe('API timeout')
            ->and($message->fresh()->failed_at)->not->toBeNull();
    });

    it('marks message as delivered', function () {
        $message = WhatsAppMessage::factory()->sent()->create();
        $message->markAsDelivered();

        expect($message->fresh()->status)->toBe(WhatsAppMessageStatus::Delivered)
            ->and($message->fresh()->delivered_at)->not->toBeNull();
    });

    it('marks message as read', function () {
        $message = WhatsAppMessage::factory()->delivered()->create();
        $message->markAsRead();

        expect($message->fresh()->status)->toBe(WhatsAppMessageStatus::Read)
            ->and($message->fresh()->read_at)->not->toBeNull();
    });

    it('has shop relationship', function () {
        $message = WhatsAppMessage::factory()->forShop($this->shop)->create();

        expect($message->shop->id)->toBe($this->shop->id);
    });

    it('has customer relationship', function () {
        $message = WhatsAppMessage::factory()->forCustomer($this->customer)->create();

        expect($message->customer->id)->toBe($this->customer->id);
    });

    it('has sender relationship', function () {
        $message = WhatsAppMessage::factory()->create(['sent_by' => $this->user->id]);

        expect($message->sender->id)->toBe($this->user->id);
    });

    it('scopes messages by shop', function () {
        WhatsAppMessage::factory()->forShop($this->shop)->count(3)->create();
        WhatsAppMessage::factory()->count(2)->create();

        expect(WhatsAppMessage::forShop($this->shop->id)->count())->toBe(3);
    });

    it('scopes messages by customer', function () {
        WhatsAppMessage::factory()->forCustomer($this->customer)->count(2)->create();
        WhatsAppMessage::factory()->count(3)->create();

        expect(WhatsAppMessage::forCustomer($this->customer->id)->count())->toBe(2);
    });

    it('creates factory with template state', function () {
        $message = WhatsAppMessage::factory()->template('welcome_message')->create();

        expect($message->message_type)->toBe(WhatsAppMessageType::Template)
            ->and($message->template_name)->toBe('welcome_message');
    });

    it('creates factory with failed state', function () {
        $message = WhatsAppMessage::factory()->failed()->create();

        expect($message->status)->toBe(WhatsAppMessageStatus::Failed)
            ->and($message->error_message)->not->toBeNull()
            ->and($message->failed_at)->not->toBeNull();
    });
});

// --- SendWhatsAppToCustomer action tests ---

describe('SendWhatsAppToCustomer action', function () {
    it('sends text message and logs it', function () {
        config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
        config(['services.whatsapp.api_token' => 'test-token']);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.action123']],
            ], 200),
        ]);

        $action = app(SendWhatsAppToCustomer::class);
        $message = $action->execute($this->customer, 'Welcome to our gym!', $this->user->id);

        expect($message->shop_id)->toBe($this->shop->id)
            ->and($message->customer_id)->toBe($this->customer->id)
            ->and($message->phone)->toBe('+254712345678')
            ->and($message->content)->toBe('Welcome to our gym!')
            ->and($message->status)->toBe(WhatsAppMessageStatus::Sent)
            ->and($message->whatsapp_message_id)->toBe('wamid.action123')
            ->and($message->direction)->toBe(WhatsAppMessageDirection::Outbound);
    });

    it('marks message as failed when API fails', function () {
        config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
        config(['services.whatsapp.api_token' => 'test-token']);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => 'Bad request'], 400),
        ]);

        $action = app(SendWhatsAppToCustomer::class);
        $message = $action->execute($this->customer, 'Hello!', $this->user->id);

        expect($message->status)->toBe(WhatsAppMessageStatus::Failed)
            ->and($message->error_message)->not->toBeNull();
    });

    it('throws exception when customer has no phone', function () {
        $customer = Customer::factory()->withoutPhone()->forShop($this->shop)->create([
            'created_by' => $this->user->id,
        ]);

        $action = app(SendWhatsAppToCustomer::class);

        expect(fn () => $action->execute($customer, 'Hello', $this->user->id))
            ->toThrow(\InvalidArgumentException::class, 'Customer does not have a phone number');
    });

    it('sends template message to customer', function () {
        config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
        config(['services.whatsapp.api_token' => 'test-token']);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.tpl456']],
            ], 200),
        ]);

        $action = app(SendWhatsAppToCustomer::class);
        $message = $action->executeTemplate($this->customer, 'welcome_template', $this->user->id);

        expect($message->message_type)->toBe(WhatsAppMessageType::Template)
            ->and($message->template_name)->toBe('welcome_template')
            ->and($message->status)->toBe(WhatsAppMessageStatus::Sent);
    });

    it('persists message in database', function () {
        config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
        config(['services.whatsapp.api_token' => 'test-token']);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.db123']],
            ], 200),
        ]);

        $action = app(SendWhatsAppToCustomer::class);
        $action->execute($this->customer, 'Persisted message', $this->user->id);

        $this->assertDatabaseHas('whatsapp_messages', [
            'customer_id' => $this->customer->id,
            'phone' => '+254712345678',
            'content' => 'Persisted message',
        ]);
    });
});

// --- CustomerWhatsAppNotification tests ---

describe('CustomerWhatsAppNotification', function () {
    it('uses whatsapp channel when configured', function () {
        config(['services.whatsapp.api_url' => 'https://api.example.com']);
        config(['services.whatsapp.api_token' => 'token']);

        $notification = new CustomerWhatsAppNotification($this->customer, 'Hello!');
        $channels = $notification->via($this->customer);

        expect($channels)->toContain(WhatsAppChannel::class);
    });

    it('excludes whatsapp channel when not configured', function () {
        config(['services.whatsapp.api_url' => null]);
        config(['services.whatsapp.api_token' => null]);

        $notification = new CustomerWhatsAppNotification($this->customer, 'Hello!');
        $channels = $notification->via($this->customer);

        expect($channels)->not->toContain(WhatsAppChannel::class);
    });

    it('returns message in toWhatsApp', function () {
        $notification = new CustomerWhatsAppNotification($this->customer, 'Welcome to our gym!');

        expect($notification->toWhatsApp($this->customer))->toBe('Welcome to our gym!');
    });

    it('queues on whatsapp-notifications queue', function () {
        $notification = new CustomerWhatsAppNotification($this->customer, 'Test');

        expect($notification->queue)->toBe('whatsapp-notifications');
    });

    it('includes customer data in array representation', function () {
        $notification = new CustomerWhatsAppNotification($this->customer, 'Hello!');
        $data = $notification->toArray($this->customer);

        expect($data)->toHaveKeys(['customer_id', 'customer_name', 'message'])
            ->and($data['customer_id'])->toBe($this->customer->id)
            ->and($data['message'])->toBe('Hello!');
    });
});

// --- Customer model WhatsApp integration ---

describe('Customer WhatsApp integration', function () {
    it('routes notification to phone number for whatsapp', function () {
        expect($this->customer->routeNotificationForWhatsapp())->toBe('+254712345678');
    });

    it('has whatsapp messages relationship', function () {
        WhatsAppMessage::factory()->forCustomer($this->customer)->forShop($this->shop)->count(3)->create();

        expect($this->customer->whatsappMessages)->toHaveCount(3);
    });

    it('can receive whatsapp notification', function () {
        Notification::fake();

        $this->customer->notify(new CustomerWhatsAppNotification($this->customer, 'Welcome!'));

        Notification::assertSentTo($this->customer, CustomerWhatsAppNotification::class);
    });
});
