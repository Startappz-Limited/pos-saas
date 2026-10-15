<?php

use App\Models\Register;
use App\Models\Shop;
use App\Services\InvoiceNumberService;
use Illuminate\Support\Facades\DB;

/**
 * Invoice numbers per register (stage 1b, decision 4.9). Concurrency is
 * covered on MySQL by InvoiceNumberConcurrencyTest.
 */
beforeEach(function () {
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->numbers = app(InvoiceNumberService::class);
    $this->year = now()->format('Y');
});

it('numbers each register of a shop in its own series', function () {
    $second = Register::factory()->create(['shop_id' => $this->shop->id, 'code' => 'R2']);
    $default = Register::defaultFor($this->shop->id);

    expect([
        $this->numbers->next($this->shop, $default),
        $this->numbers->next($this->shop, $second),
        $this->numbers->next($this->shop, $default),
        $this->numbers->next($this->shop->id, $second->id),
    ])->toBe([
        "INV-NRB-R1-{$this->year}-000001",
        "INV-NRB-R2-{$this->year}-000001",
        "INV-NRB-R1-{$this->year}-000002",
        "INV-NRB-R2-{$this->year}-000002",
    ])
        ->and($default->fresh()->last_invoice_number)->toBe(2)
        ->and($second->fresh()->last_invoice_number)->toBe(2);
});

it('numbers a sale that names no register on the shop\'s default register', function () {
    expect($this->numbers->next($this->shop))->toBe("INV-NRB-R1-{$this->year}-000001");
});

it('carries a shop\'s existing series on at its default register when migrated', function () {
    $default = Register::defaultFor($this->shop->id);
    DB::table('invoice_sequences')->insert([
        'shop_id' => $this->shop->id, 'series' => $this->year, 'next_number' => 124,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    (require database_path('migrations/2026_10_15_000004_move_shop_invoice_series_onto_default_registers.php'))->up();

    expect($default->fresh()->last_invoice_number)->toBe(123)
        ->and($this->numbers->next($this->shop))->toBe("INV-NRB-R1-{$this->year}-000124");
});
