<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\CreditBalanceNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\SaleCompletedNotification;
use Database\Factories\BusinessFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->shop = Shop::factory()->create();
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);
    $this->customer = Customer::factory()->forShop($this->shop)->create([
        'phone' => '+254712345678',
        'created_by' => $this->user->id,
    ]);

    Permission::create(['name' => 'sales.full-access']);
    $this->user->givePermissionTo('sales.full-access');
});

// --- SaleCompletedNotification ---

describe('SaleCompletedNotification', function () {
    it('sends via WhatsApp channel when configured and customer has phone', function () {
        config(['services.whatsapp.api_url' => 'https://api.example.com']);
        config(['services.whatsapp.api_token' => 'test-token']);

        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-TEST001',
            'total_amount' => 1500.00,
            'payment_method' => 'cash',
        ]);

        $notification = new SaleCompletedNotification($sale);
        $channels = $notification->via($this->customer);

        expect($channels)->toContain(WhatsAppChannel::class);
    });

    it('does not send when customer has no phone', function () {
        config(['services.whatsapp.api_url' => 'https://api.example.com']);
        config(['services.whatsapp.api_token' => 'test-token']);

        $customerNoPhone = Customer::factory()->forShop($this->shop)->create([
            'phone' => null,
            'created_by' => $this->user->id,
        ]);

        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customerNoPhone->id,
        ]);

        $notification = new SaleCompletedNotification($sale);
        $channels = $notification->via($customerNoPhone);

        expect($channels)->toBeEmpty();
    });

    it('formats receipt message with items', function () {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-RECEIPT1',
            'subtotal' => 2500.00,
            'discount_amount' => 100.00,
            'delivery_fee' => 200.00,
            'total_amount' => 2600.00,
            'payment_method' => 'cash',
            'balance_due' => 0,
        ]);

        $product = Product::factory()->create(['name' => 'Protein Powder']);
        SaleItem::create([
            'uuid' => (string) Str::uuid(),
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 1250.00,
            'line_total' => 2500.00,
            'unit_cost' => 800.00,
            'total_cost' => 1600.00,
            'profit' => 900.00,
            'profit_margin' => 36.00,
            'status' => 'completed',
        ]);

        $notification = new SaleCompletedNotification($sale);
        $message = $notification->toWhatsApp($this->customer);

        expect($message)
            ->toContain('INV-RECEIPT1')
            ->toContain('Protein Powder')
            ->toContain('x2')
            ->toContain('2,600.00')
            ->toContain('Discount: -100.00')
            ->toContain('Delivery: 200.00')
            ->toContain('Cash')
            ->toContain('Thank you');
    });

    it('shows balance due for unpaid sales', function () {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'total_amount' => 1000.00,
            'balance_due' => 500.00,
            'payment_method' => 'cash',
        ]);

        $notification = new SaleCompletedNotification($sale);
        $message = $notification->toWhatsApp($this->customer);

        expect($message)->toContain('Balance Due: 500.00');
    });

    it('is queued on whatsapp-notifications queue', function () {
        $sale = Sale::factory()->create(['shop_id' => $this->shop->id]);
        $notification = new SaleCompletedNotification($sale);

        expect($notification->queue)->toBe('whatsapp-notifications');
    });
});

// --- PaymentReceivedNotification ---

describe('PaymentReceivedNotification', function () {
    it('sends via WhatsApp channel when configured and customer has phone', function () {
        config(['services.whatsapp.api_url' => 'https://api.example.com']);
        config(['services.whatsapp.api_token' => 'test-token']);

        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
        ]);

        $notification = new PaymentReceivedNotification($sale, 500.00);
        $channels = $notification->via($this->customer);

        expect($channels)->toContain(WhatsAppChannel::class);
    });

    it('formats payment message with amounts', function () {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-PAY001',
            'total_amount' => 2000.00,
            'paid_amount' => 1500.00,
            'balance_due' => 500.00,
        ]);

        $notification = new PaymentReceivedNotification($sale, 1000.00);
        $message = $notification->toWhatsApp($this->customer);

        expect($message)
            ->toContain('Payment Received')
            ->toContain('INV-PAY001')
            ->toContain('Amount Paid: 1,000.00')
            ->toContain('Total Paid: 1,500.00 / 2,000.00')
            ->toContain('Remaining Balance: 500.00');
    });

    it('shows fully paid when no balance', function () {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'total_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'balance_due' => 0,
        ]);

        $notification = new PaymentReceivedNotification($sale, 500.00);
        $message = $notification->toWhatsApp($this->customer);

        expect($message)->toContain('Fully Paid');
    });

    it('includes amount paid in toArray', function () {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'balance_due' => 200.00,
        ]);

        $notification = new PaymentReceivedNotification($sale, 800.00);
        $array = $notification->toArray($this->customer);

        expect($array)
            ->toHaveKey('amount_paid', 800.00)
            ->toHaveKey('balance_due', 200.00);
    });
});

// --- CreditBalanceNotification ---

describe('CreditBalanceNotification', function () {
    it('sends via WhatsApp channel when configured and customer has phone', function () {
        config(['services.whatsapp.api_url' => 'https://api.example.com']);
        config(['services.whatsapp.api_token' => 'test-token']);

        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
        ]);

        $notification = new CreditBalanceNotification($sale);
        $channels = $notification->via($this->customer);

        expect($channels)->toContain(WhatsAppChannel::class);
    });

    it('formats credit sale message', function () {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-CREDIT1',
            'total_amount' => 3000.00,
            'payment_method' => 'credit',
        ]);

        $this->customer->update([
            'credit_balance' => 5000.00,
            'credit_limit' => 10000.00,
        ]);

        $notification = new CreditBalanceNotification($sale);
        $message = $notification->toWhatsApp($this->customer->fresh());

        expect($message)
            ->toContain('Credit Sale Recorded')
            ->toContain('INV-CREDIT1')
            ->toContain('Amount Due: 3,000.00')
            ->toContain('Total Credit Balance: 5,000.00')
            ->toContain('Available Credit: 5,000.00')
            ->toContain('settle your balance');
    });

    it('does not show credit info when customer has no credit limit', function () {
        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'invoice_number' => 'INV-CREDIT2',
            'total_amount' => 1000.00,
        ]);

        $this->customer->update([
            'credit_balance' => 0,
            'credit_limit' => 0,
        ]);

        $notification = new CreditBalanceNotification($sale);
        $message = $notification->toWhatsApp($this->customer->fresh());

        expect($message)
            ->toContain('Amount Due: 1,000.00')
            ->not->toContain('Available Credit');
    });
});

// --- WhatsAppChannel DB Logging ---

describe('WhatsAppChannel DB Logging', function () {
    it('logs messages to whatsapp_messages table on successful send', function () {
        config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
        config(['services.whatsapp.api_token' => 'test-token']);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test123']],
            ], 200),
        ]);

        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
        ]);

        $channel = app(WhatsAppChannel::class);
        $notification = new SaleCompletedNotification($sale);
        $channel->send($this->customer, $notification);

        $logged = WhatsAppMessage::where('phone', '+254712345678')->first();

        expect($logged)->not->toBeNull()
            ->and($logged->shop_id)->toBe($this->shop->id)
            ->and($logged->customer_id)->toBe($this->customer->id)
            ->and($logged->status->value)->toBe('sent')
            ->and($logged->whatsapp_message_id)->toBe('wamid.test123');
    });

    it('logs failed messages with error', function () {
        config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
        config(['services.whatsapp.api_token' => 'test-token']);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['message' => 'Invalid phone'],
            ], 400),
        ]);

        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
        ]);

        $channel = app(WhatsAppChannel::class);
        $notification = new SaleCompletedNotification($sale);
        $channel->send($this->customer, $notification);

        $logged = WhatsAppMessage::where('phone', '+254712345678')->first();

        expect($logged)->not->toBeNull()
            ->and($logged->status->value)->toBe('failed')
            ->and($logged->error_message)->not->toBeNull();
    });

    it('does not log when notifiable has no shop_id', function () {
        config(['services.whatsapp.api_url' => 'https://graph.facebook.com/v22.0/123/messages']);
        config(['services.whatsapp.api_token' => 'test-token']);

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.test456']],
            ], 200),
        ]);

        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
        ]);

        // Use a notifiable with no shop_id
        $notifiable = new class
        {
            public ?string $phone = '+254712345678';

            public ?int $shop_id = null;

            public function routeNotificationFor(): ?string
            {
                return $this->phone;
            }
        };

        $channel = app(WhatsAppChannel::class);
        $notification = new SaleCompletedNotification($sale);
        $channel->send($notifiable, $notification);

        expect(WhatsAppMessage::count())->toBe(0);
    });
});

// --- SaleController WhatsApp Integration ---

describe('SaleController WhatsApp notifications', function () {
    beforeEach(function () {
        $this->saleSource = SaleSource::firstOrCreate(
            ['business_id' => BusinessFactory::defaultId(), 'name' => 'Walk-in'],
            ['is_active' => true, 'sort_order' => 1]
        );
    });

    it('sends SaleCompletedNotification when sale is completed via store', function () {
        Notification::fake();

        $this->actingAs($this->user);
        $product = Product::factory()->create(['stock_quantity' => 50]);

        $response = $this->post(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'source_id' => $this->saleSource->id,
            'delivery_location' => 'Nairobi CBD',
            'expense_notes' => null,
            'payment_method' => 'cash',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 100.00,
                ],
            ],
        ]);

        Notification::assertSentTo($this->customer, SaleCompletedNotification::class);
    });

    it('sends CreditBalanceNotification for credit sales', function () {
        Notification::fake();

        $this->actingAs($this->user);
        $product = Product::factory()->create(['stock_quantity' => 50]);

        // customer_type must be forced: CustomerFactory randomises it between
        // retail and wholesale, and StoreSaleRequest rejects credit sales for
        // retail customers — which made this test a 50/50 coin flip.
        $this->customer->update([
            'customer_type' => 'wholesale',
            'allow_credit' => true,
            'credit_limit' => 10000,
        ]);

        $response = $this->post(route('sales.store'), [
            'customer_id' => $this->customer->id,
            'source_id' => $this->saleSource->id,
            'delivery_location' => 'Nairobi CBD',
            'expense_notes' => null,
            'payment_method' => 'credit',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 100.00,
                ],
            ],
        ]);

        Notification::assertSentTo($this->customer, CreditBalanceNotification::class);
        Notification::assertNotSentTo($this->customer, SaleCompletedNotification::class);
    });

    it('does not send notification for walk-in sales without customer', function () {
        Notification::fake();

        $this->actingAs($this->user);
        $product = Product::factory()->create(['stock_quantity' => 50]);

        $this->post(route('sales.store'), [
            'customer_id' => null,
            'source_id' => $this->saleSource->id,
            'delivery_location' => 'Nairobi CBD',
            'expense_notes' => null,
            'payment_method' => 'cash',
            'walk_in_customer_name' => 'Walk In',
            'walk_in_customer_phone' => '+254700000000',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => 100.00,
                ],
            ],
        ]);

        Notification::assertNothingSent();
    });

    it('sends SaleCompletedNotification when pending sale is completed', function () {
        Notification::fake();

        $this->actingAs($this->user);

        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'payment_method' => 'cash',
            'completed_at' => null,
        ]);

        $this->post(route('sales.complete', $sale));

        Notification::assertSentTo($this->customer, SaleCompletedNotification::class);
    });

    it('sends PaymentReceivedNotification on payment collection', function () {
        Notification::fake();

        $this->actingAs($this->user);

        $sale = Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'total_amount' => 1000.00,
            'paid_amount' => 0,
            'balance_due' => 1000.00,
            'payment_status' => 'unpaid',
            'status' => 'pending',
            'is_cod' => true,
        ]);

        $this->postJson(route('sales.collectPayment', $sale), [
            'payments' => [
                [
                    'amount' => 500.00,
                    'payment_method' => 'cash',
                ],
            ],
        ]);

        Notification::assertSentTo($this->customer, PaymentReceivedNotification::class, function ($notification) {
            return $notification->amountPaid === 500.0;
        });
    });
});
