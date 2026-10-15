<?php

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Registers (tills) and the cash sessions that run on them (stage 1b,
 * decisions 4.2 and 4.8).
 */
beforeEach(function () {
    $this->shop = Shop::factory()->create(['code' => 'MAIN']);
    $this->cashier = User::factory()->create();
    $this->cashier->shops()->attach($this->shop);
    $this->actingAs($this->cashier);
});

it('gives every new shop a default register', function () {
    $default = Register::where('shop_id', $this->shop->id)->sole();

    expect($default->code)->toBe(Register::DEFAULT_CODE)
        ->and(Register::defaultFor($this->shop->id)->id)->toBe($default->id);
});

it('opens and closes a cash session on a register', function () {
    $second = Register::factory()->create(['shop_id' => $this->shop->id, 'code' => 'R2']);

    $session = CashRegister::openRegister($this->shop->id, 500, registerId: $second->id);

    expect($session->register_id)->toBe($second->id)
        ->and(CashRegister::getActiveRegister($this->shop->id, $second->id)?->id)->toBe($session->id)
        // The default register has no session open.
        ->and(CashRegister::getActiveRegister($this->shop->id))->toBeNull();

    $session->closeRegister(500);

    expect($session->fresh()->status)->toBe('closed')
        ->and(CashRegister::getActiveRegister($this->shop->id, $second->id))->toBeNull();
});

it('runs a session on each register of a shop at the same time', function () {
    $second = Register::factory()->create(['shop_id' => $this->shop->id, 'code' => 'R2']);

    $onDefault = CashRegister::openRegister($this->shop->id, 100);
    $onSecond = CashRegister::openRegister($this->shop->id, 200, registerId: $second->id);

    expect($onDefault->id)->not->toBe($onSecond->id)
        ->and($onDefault->fresh()->status)->toBe('open')
        ->and($onSecond->fresh()->status)->toBe('open');
});

it('lands a legacy cash-register call and a legacy sale on the shop\'s default register', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $default = Register::defaultFor($this->shop->id);

    $opened = $this->postJson('/api/cash-registers/open', ['opening_balance' => 1000])->assertCreated();
    $session = CashRegister::where('uuid', $opened->json('data.register.uuid'))->sole();

    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 5, 'selling_price' => 100]);
    $uuid = $this->postJson('/api/sales', [
        'shop_id' => $this->shop->id,
        'source_id' => DB::table('sale_sources')->where('business_id', testBusiness()->id)->value('id'),
        'delivery_location' => 'In store',
        'walk_in_customer_name' => 'Walk In',
        'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 100]],
    ])->assertCreated()->json('data.uuid');

    expect($session->register_id)->toBe($default->id)
        ->and(Sale::where('uuid', $uuid)->sole()->register_id)->toBe($session->id);
});

it('places every existing session on its shop\'s default register when migrated', function () {
    $session = CashRegister::openRegister($this->shop->id, 100);
    DB::table('cash_registers')->where('id', $session->id)->update(['register_id' => null]);
    DB::table('registers')->delete();

    (require database_path('migrations/2026_10_15_000003_create_default_registers_and_place_sessions.php'))->up();

    $default = Register::where('shop_id', $this->shop->id)->sole();
    expect($default->code)->toBe('R1')
        ->and($session->fresh()->register_id)->toBe($default->id);
});
