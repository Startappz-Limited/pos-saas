<?php

use App\Actions\Sale\RecordSale;
use App\Contracts\FiscalProvider;
use App\Models\Register;
use App\Models\Sale;
use App\Services\Fiscal\FiscalSignature;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * Receipts and the fiscal seam, Phase 7 (step 10): a sale's receipt as
 * structured data for an ESC/POS printer, for whoever may see the sale; the
 * fiscal block is empty until eTIMS is connected, and filled by a provider
 * once one is.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    $this->sale = $this->a->sale;
    $this->tenant->forget();
});

function receiptFor(string $token, Sale $sale)
{
    Till::freshRequest();

    return test()->withToken($token)->getJson("/api/v1/sales/{$sale->uuid}/receipt");
}

it('gives a sale\'s receipt in sections a printer can lay out, with money as strings', function () {
    // A sale with a line, made as the till's flat API makes it.
    $this->actingAs($this->a->owner);
    $this->sale = $this->tenant->run($this->a->business, fn () => app(RecordSale::class)->execute([
        'source_id' => DB::table('sale_sources')->where('business_id', $this->a->business->id)->value('id'),
        'delivery_location' => 'In store', 'walk_in_customer_name' => 'Walk-in', 'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash', 'items' => [['product_id' => $this->a->product->id, 'quantity' => 2, 'price' => 150]],
    ])->sale);

    $receipt = receiptFor(Till::signIn($this->a->owner), $this->sale)->assertOk()
        ->assertJsonStructure(['data' => [
            'header' => ['business', 'shop', 'address', 'phone', 'tax_pin', 'vat_registered', 'text'],
            'sale' => ['uuid', 'invoice_number', 'date', 'till', 'cashier', 'customer'],
            'lines' => [['name', 'quantity', 'unit_price', 'total', 'tax_code']],
            'totals' => ['subtotal', 'discount', 'tax', 'total', 'paid', 'balance_due'],
            'tax', 'payments', 'footer', 'fiscal',
        ]])
        ->json('data');

    expect($receipt['sale']['invoice_number'])->toBe($this->sale->invoice_number)
        ->and($receipt['lines'][0]['quantity'])->toBe(2)
        ->and($receipt['lines'][0]['total'])->toBe('300.00')
        ->and($receipt['payments'][0]['method'] ?? 'cash')->toBe('cash')
        ->and($receipt['totals']['total'])->toBe(number_format((float) $this->sale->total_amount, 2, '.', ''))
        ->and($receipt['header']['business'])->toBe($this->a->business->name)
        ->and($receipt['fiscal'])->toBeNull();
});

it('prints the fiscal block once a provider signs the sale', function () {
    app()->instance(FiscalProvider::class, new class implements FiscalProvider
    {
        public function signatureFor(Sale $sale): ?FiscalSignature
        {
            return new FiscalSignature('etims', 'CU-123', 'KRA0001', 'INV-1', 'https://etims.example/qr/1');
        }
    });

    receiptFor(Till::signIn($this->a->owner), $this->sale)->assertOk()
        ->assertJsonPath('data.fiscal.control_code', 'CU-123')
        ->assertJsonPath('data.fiscal.qr_url', 'https://etims.example/qr/1');
});

it('refuses another business\'s sale, and another shop\'s sale to a till', function () {
    receiptFor(Till::signIn($this->a->owner), $this->fixture->b->sale)->assertNotFound();

    // A till of the other shop of A can't print this shop's sale.
    $otherShop = collect($this->a->shops)->first(fn ($shop) => $shop->id !== $this->sale->shop_id);
    $register = $this->tenant->run($this->a->business, fn () => Register::defaultFor($otherShop->id));
    $this->tenant->forget();

    receiptFor(Till::token($this->a->owner, $register), $this->sale)->assertNotFound();
});
