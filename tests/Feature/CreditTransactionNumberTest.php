<?php

use App\Models\CreditTransaction;
use App\Models\Shop;
use App\Models\User;

/**
 * Credit transaction numbers are unique system-wide, whoever issues them. They
 * were today's count plus one, counted through the shop-access scope, so a
 * cashier who could see only one shop issued a number another shop already had
 * (found by the live-like dataset build, stage 1c).
 */
it('issues a fresh number to a cashier who sees only one shop', function () {
    $shopA = Shop::factory()->create();
    $shopB = Shop::factory()->create();

    // Shop A issues today's first number.
    $first = CreditTransaction::factory()->create(['shop_id' => $shopA->id, 'transaction_number' => null]);

    // A cashier of shop B only, who can't see A's transaction.
    $cashier = User::factory()->create();
    $cashier->shops()->attach($shopB);
    $this->actingAs($cashier);

    $second = CreditTransaction::factory()->create(['shop_id' => $shopB->id, 'transaction_number' => null]);

    expect($first->transaction_number)->toStartWith('CTX-'.now()->format('ymd').'-')
        ->and($second->transaction_number)->not->toBe($first->transaction_number);
});
