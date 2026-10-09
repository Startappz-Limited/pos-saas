<?php

use App\Actions\Baileys\NotifyCustomerOfSale;
use App\Actions\SendSaleNotifications;
use App\Jobs\SendSaleInvoiceViaBaileysJob;
use App\Models\BaileysSession;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\SaleCompletedNotification;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('wa-gateway.url', 'https://gateway.test');
    config()->set('wa-gateway.token', 'test-token');
    config()->set('baileys.notify_on_sale', true);

    $this->seed(PermissionSeeder::class);
});

function actingAsBaileysManager(): User
{
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['baileys.view', 'baileys.manage']);
    test()->actingAs($user);

    return $user;
}

/**
 * A completed sale for a reachable customer in a shop with a connected session.
 */
function saleForShop(Shop $shop): Sale
{
    $customer = Customer::factory()->create(['phone' => '254712345678']);
    $sale = Sale::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id]);
    SaleItem::factory()->for($sale)->create(['shop_id' => $shop->id]);

    return $sale->fresh();
}

describe('sale notification settings', function () {
    it('defaults every switch on so behaviour is unchanged out of the box', function () {
        $shop = Shop::factory()->create(['settings' => null]);

        expect($shop->notifiesOnSale())->toBeTrue()
            ->and($shop->notifiesInvoiceOnSale())->toBeTrue()
            ->and($shop->notifiesReceiptOnSale())->toBeTrue();
    });

    it('honours the BAILEYS_NOTIFY_ON_SALE default for the invoice channel only', function () {
        config()->set('baileys.notify_on_sale', false);

        $shop = Shop::factory()->create(['settings' => null]);

        expect($shop->notifiesInvoiceOnSale())->toBeFalse()
            ->and($shop->notifiesReceiptOnSale())->toBeTrue()
            ->and($shop->notifiesOnSale())->toBeTrue();
    });

    it('lets the master switch turn every channel off at once', function () {
        $shop = Shop::factory()->create();
        $shop->setSaleNotification(Shop::SALE_NOTIFY_MASTER, false);
        $shop->save();

        $shop = $shop->fresh();

        expect($shop->notifiesInvoiceOnSale())->toBeFalse()
            ->and($shop->notifiesReceiptOnSale())->toBeFalse();
    });

    it('remembers each channel position while the master is off', function () {
        $shop = Shop::factory()->create();
        $shop->setSaleNotification(Shop::SALE_NOTIFY_TEXT_RECEIPT, false);
        $shop->setSaleNotification(Shop::SALE_NOTIFY_MASTER, false);
        $shop->save();

        // Master back on: the invoice channel returns, the text receipt stays off.
        $shop->setSaleNotification(Shop::SALE_NOTIFY_MASTER, true);
        $shop->save();

        $shop = $shop->fresh();

        expect($shop->notifiesInvoiceOnSale())->toBeTrue()
            ->and($shop->notifiesReceiptOnSale())->toBeFalse();
    });

    it('switches channels independently of each other', function () {
        $shop = Shop::factory()->create();
        $shop->setSaleNotification(Shop::SALE_NOTIFY_INVOICE_PDF, false);
        $shop->save();

        $shop = $shop->fresh();

        expect($shop->notifiesInvoiceOnSale())->toBeFalse()
            ->and($shop->notifiesReceiptOnSale())->toBeTrue();
    });

    it('preserves unrelated settings when toggled', function () {
        $shop = Shop::factory()->create(['settings' => ['integrations' => ['woocommerce' => ['enabled' => true]]]]);

        $shop->setSaleNotification(Shop::SALE_NOTIFY_MASTER, false);
        $shop->save();

        expect($shop->fresh()->settings['integrations']['woocommerce']['enabled'])->toBeTrue()
            ->and($shop->fresh()->notifiesOnSale())->toBeFalse();
    });
});

describe('sale notification toggle endpoint', function () {
    it('toggles each channel', function (string $channel) {
        actingAsBaileysManager();
        $shop = Shop::factory()->create();

        $this->post(route('baileys.automation.saleNotifications', $shop), [
            'channel' => $channel,
            'enabled' => 0,
        ])->assertRedirect();

        expect($shop->fresh()->saleNotificationEnabled($channel))->toBeFalse();

        $this->post(route('baileys.automation.saleNotifications', $shop), [
            'channel' => $channel,
            'enabled' => 1,
        ])->assertRedirect();

        expect($shop->fresh()->saleNotificationEnabled($channel))->toBeTrue();
    })->with([
        Shop::SALE_NOTIFY_MASTER,
        Shop::SALE_NOTIFY_INVOICE_PDF,
        Shop::SALE_NOTIFY_TEXT_RECEIPT,
    ]);

    it('rejects an unknown channel', function () {
        actingAsBaileysManager();
        $shop = Shop::factory()->create();

        $this->post(route('baileys.automation.saleNotifications', $shop), [
            'channel' => 'carrier_pigeon',
            'enabled' => 0,
        ])->assertSessionHasErrors('channel');
    });

    it('validates the enabled flag', function () {
        actingAsBaileysManager();
        $shop = Shop::factory()->create();

        $this->post(route('baileys.automation.saleNotifications', $shop), [
            'channel' => Shop::SALE_NOTIFY_MASTER,
            'enabled' => 'maybe',
        ])->assertSessionHasErrors('enabled');
    });

    it('denies a user without the baileys.manage permission', function () {
        $user = User::factory()->owner()->create();
        $user->givePermissionTo('baileys.view');
        $this->actingAs($user);

        $shop = Shop::factory()->create();

        $this->post(route('baileys.automation.saleNotifications', $shop), [
            'channel' => Shop::SALE_NOTIFY_MASTER,
            'enabled' => 0,
        ])->assertForbidden();

        expect($shop->fresh()->notifiesOnSale())->toBeTrue();
    });

    it('requires authentication', function () {
        $shop = Shop::factory()->create();

        $this->post(route('baileys.automation.saleNotifications', $shop), [
            'channel' => Shop::SALE_NOTIFY_MASTER,
            'enabled' => 0,
        ])->assertRedirect(route('login'));
    });

    it('shows all three switches on the sessions screen', function () {
        actingAsBaileysManager();
        Shop::factory()->create(['name' => 'Westlands Branch']);

        $this
            ->get(route('baileys.sessions.index'))
            ->assertOk()
            ->assertViewHas('automationShops')
            ->assertSee('Automatic Messages')
            ->assertSee('All sale messages')
            ->assertSee('Invoice PDF')
            ->assertSee('Text receipt')
            ->assertSee('Westlands Branch');
    });
});

describe('the switches actually gate delivery', function () {
    it('sends nothing at all when the master switch is off', function () {
        Queue::fake();
        Notification::fake();

        $shop = Shop::factory()->create();
        $shop->setSaleNotification(Shop::SALE_NOTIFY_MASTER, false);
        $shop->save();

        app(SendSaleNotifications::class)->execute(saleForShop($shop));

        Queue::assertNotPushed(SendSaleInvoiceViaBaileysJob::class);
        Notification::assertNothingSent();
    });

    it('sends only the text receipt when the invoice channel is off', function () {
        Queue::fake();
        Notification::fake();

        $shop = Shop::factory()->create();
        $shop->setSaleNotification(Shop::SALE_NOTIFY_INVOICE_PDF, false);
        $shop->save();

        app(SendSaleNotifications::class)->execute(saleForShop($shop));

        Queue::assertNotPushed(SendSaleInvoiceViaBaileysJob::class);
        Notification::assertSentTimes(SaleCompletedNotification::class, 1);
    });

    it('sends only the invoice PDF when the text receipt is off', function () {
        Queue::fake();
        Notification::fake();

        $shop = Shop::factory()->create();
        $shop->setSaleNotification(Shop::SALE_NOTIFY_TEXT_RECEIPT, false);
        $shop->save();

        app(SendSaleNotifications::class)->execute(saleForShop($shop));

        Queue::assertPushed(SendSaleInvoiceViaBaileysJob::class);
        Notification::assertNothingSent();
    });

    it('sends both when everything is on', function () {
        Queue::fake();
        Notification::fake();

        $shop = Shop::factory()->create();

        app(SendSaleNotifications::class)->execute(saleForShop($shop));

        Queue::assertPushed(SendSaleInvoiceViaBaileysJob::class);
        Notification::assertSentTimes(SaleCompletedNotification::class, 1);
    });

    it('stops the bridge call itself when the invoice channel is off', function () {
        Http::fake();

        $shop = Shop::factory()->create();
        $shop->setSaleNotification(Shop::SALE_NOTIFY_INVOICE_PDF, false);
        $shop->save();

        BaileysSession::factory()->for($shop)->connected()->create();

        expect(app(NotifyCustomerOfSale::class)->execute(saleForShop($shop)))->toBeNull();
        Http::assertNothingSent();
    });

    it('still sends for a shop that left its switches on', function () {
        Http::fake([
            'gateway.test/sessions/*/messages/media' => Http::response(['wa_message_id' => 'WAID-PDF'], 200),
        ]);

        $off = Shop::factory()->create();
        $off->setSaleNotification(Shop::SALE_NOTIFY_MASTER, false);
        $off->save();

        $on = Shop::factory()->create();
        BaileysSession::factory()->for($on)->connected()->create();

        expect(app(NotifyCustomerOfSale::class)->execute(saleForShop($on)))->not->toBeNull();
    });
});
