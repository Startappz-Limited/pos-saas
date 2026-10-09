<?php

use App\Actions\Baileys\NotifyCustomerOfSale;
use App\Actions\GenerateSaleInvoicePdf;
use App\Actions\SendSaleNotifications;
use App\Enums\BaileysMessageType;
use App\Jobs\SendSaleInvoiceViaBaileysJob;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    config()->set('wa-gateway.url', 'https://gateway.test');
    config()->set('wa-gateway.token', 'test-token');
    config()->set('baileys.notify_on_sale', true);
});

/**
 * A completed, fully-paid sale for a customer with a WhatsApp-reachable phone,
 * in a shop that has a connected Baileys session.
 */
function saleReadyToNotify(array $overrides = []): Sale
{
    $shop = Shop::factory()->create();
    BaileysSession::factory()->for($shop)->connected()->create();

    $customer = Customer::factory()->create(['phone' => '254712345678']);

    $sale = Sale::factory()->create(array_merge([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-TEST01',
        'subtotal' => 1500.00,
        'total_amount' => 1500.00,
        'paid_amount' => 1500.00,
        'balance_due' => 0,
        'payment_status' => 'paid',
    ], $overrides));

    SaleItem::factory()->for($sale)->create([
        'shop_id' => $shop->id,
        'quantity' => 2,
        'unit_price' => 750.00,
        'line_total' => 1500.00,
    ]);

    return $sale->fresh();
}

describe('invoice PDF delivery over Baileys', function () {
    it('sends the invoice as a PDF document with the receipt as caption', function () {
        Http::fake([
            'gateway.test/sessions/*/messages/media' => Http::response(['wa_message_id' => 'WAID-PDF'], 200),
        ]);

        $sale = saleReadyToNotify();

        $message = app(NotifyCustomerOfSale::class)->execute($sale);

        expect($message)->toBeInstanceOf(BaileysMessage::class)
            ->and($message->type)->toBe(BaileysMessageType::Document)
            ->and($message->media_mime)->toBe('application/pdf')
            ->and($message->media_filename)->toBe('invoice-inv-test01.pdf')
            ->and($message->content)->toContain('INV-TEST01')
            ->and($message->content)->toContain('Status: PAID');

        Http::assertSent(function ($request) {
            $body = $request->data();

            // Bytes travel inline so the gateway never has to fetch our URL.
            // The signed link is recorded on the message instead (next test).
            return $body['type'] === 'document'
                && str_starts_with(base64_decode($body['data']), '%PDF')
                && $body['mime'] === 'application/pdf'
                && ! isset($body['url']);
        });
    });

    it('records the signed invoice URL on the message', function () {
        Http::fake([
            'gateway.test/sessions/*/messages/media' => Http::response(['wa_message_id' => 'WAID-PDF'], 200),
        ]);

        $message = app(NotifyCustomerOfSale::class)->execute(saleReadyToNotify());

        expect($message->media_url)->toContain('/invoice')
            ->and($message->media_url)->toContain('signature=');
    });

    it('shows the balance due instead of PAID when the sale is not settled', function () {
        Http::fake([
            'gateway.test/sessions/*/messages/media' => Http::response(['wa_message_id' => 'WAID-PDF'], 200),
        ]);

        $sale = saleReadyToNotify([
            'paid_amount' => 500.00,
            'balance_due' => 1000.00,
            'payment_status' => 'partial',
        ]);

        $message = app(NotifyCustomerOfSale::class)->execute($sale);

        expect($message->content)->toContain('Balance Due: 1,000.00')
            ->and($message->content)->not->toContain('Status: PAID');
    });

    it('falls back to a plain text receipt when the PDF cannot be rendered', function () {
        Http::fake([
            'gateway.test/sessions/*/messages/text' => Http::response(['wa_message_id' => 'WAID-TXT'], 200),
        ]);

        $this->mock(GenerateSaleInvoicePdf::class, function ($mock) {
            $mock->shouldReceive('execute')->andThrow(new RuntimeException('dompdf exploded'));
        });

        $message = app(NotifyCustomerOfSale::class)->execute(saleReadyToNotify());

        expect($message)->toBeInstanceOf(BaileysMessage::class)
            ->and($message->type)->toBe(BaileysMessageType::Text)
            ->and($message->content)->toContain('INV-TEST01');
    });

    it('does nothing when the shop has no connected session', function () {
        Http::fake();

        $shop = Shop::factory()->create();
        $customer = Customer::factory()->create(['phone' => '254712345678']);
        $sale = Sale::factory()->create(['shop_id' => $shop->id, 'customer_id' => $customer->id]);

        expect(app(NotifyCustomerOfSale::class)->execute($sale))->toBeNull();
        Http::assertNothingSent();
    });
});

describe('signed invoice route', function () {
    it('serves the PDF over a valid signed URL without authentication', function () {
        $sale = saleReadyToNotify();

        $response = $this->get(URL::signedRoute('sales.invoice-pdf', $sale));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
    });

    it('rejects an unsigned or tampered invoice URL', function () {
        $sale = saleReadyToNotify();

        $this->get(route('sales.invoice-pdf', $sale))->assertForbidden();
        $this->get(URL::signedRoute('sales.invoice-pdf', $sale).'x')->assertForbidden();
    });
});

describe('sale notification fan-out', function () {
    it('queues the invoice job for a completed sale', function () {
        Queue::fake();

        app(SendSaleNotifications::class)->execute(saleReadyToNotify());

        Queue::assertPushed(SendSaleInvoiceViaBaileysJob::class);
    });

    it('does not queue anything for a sale with no customer', function () {
        Queue::fake();

        app(SendSaleNotifications::class)->execute(Sale::factory()->create(['customer_id' => null]));

        Queue::assertNotPushed(SendSaleInvoiceViaBaileysJob::class);
    });

    it('sends a PAID invoice once a payment clears the balance', function () {
        Queue::fake();

        $sale = saleReadyToNotify(['payment_status' => 'paid', 'balance_due' => 0]);

        app(SendSaleNotifications::class)->paymentReceived($sale, 1500.00);

        Queue::assertPushed(SendSaleInvoiceViaBaileysJob::class);
    });

    it('does not send an invoice for a partial payment', function () {
        Queue::fake();

        $sale = saleReadyToNotify(['payment_status' => 'partial', 'balance_due' => 1000.00]);

        app(SendSaleNotifications::class)->paymentReceived($sale, 500.00);

        Queue::assertNotPushed(SendSaleInvoiceViaBaileysJob::class);
    });
});
