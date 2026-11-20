<?php

use App\Actions\Stock\TouchExpiredStock;
use App\Enums\StockMovementType;
use App\Jobs\PushShopStockJob;
use App\Models\Product;
use App\Models\Shop;
use App\Services\Stock\StockLedger;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/*
 * The expiry touch (stage 11, step 5): a batch that expires writes nothing, so the touch moves
 * the stock row's updated_at for tills to pull, once per expiry, whenever the scheduler runs.
 */

beforeEach(function () {
    Bus::fake([PushShopStockJob::class]);
    $this->ledger = app(StockLedger::class);
    $this->touch = fn (Carbon $now): int => app(TouchExpiredStock::class)->execute($now);
    // The shop's row is read back as the till's pull sees it.
    $this->touchedAt = fn (Shop $shop, Product $product) => DB::table('shop_variation')
        ->where('shop_id', $shop->id)->where('variation_id', $product->defaultVariation->id)->value('updated_at');
    $this->stocked = function (Shop $shop, ?string $expiry, int $quantity = 10): Product {
        $product = Product::factory()->create(['shop_id' => $shop->id, 'stock_quantity' => 0]);
        $this->ledger->receive($shop->id, $product->defaultVariation, $quantity, 5.0, StockMovementType::INTAKE, ['batch_number' => 'B1', 'expiry_date' => $expiry]);
        DB::table('shop_variation')->where('shop_id', $shop->id)->update(['updated_at' => '2026-01-01 00:00:00']);

        return $product;
    };
});

it('touches only the rows with a batch that expired since the shop\'s last run', function () {
    $shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi', 'expiry_touched_at' => Carbon::parse('2026-10-31 12:00:00', 'Africa/Nairobi')]);
    $expiredToday = ($this->stocked)($shop, '2026-10-31');
    $expiredBefore = ($this->stocked)($shop, '2026-10-29');
    $notYet = ($this->stocked)($shop, '2026-11-15');
    $noExpiry = ($this->stocked)($shop, null);

    // 2026-10-31 expired at 00:00 on 1 November, Nairobi: after the last run, before this one.
    $touched = ($this->touch)(Carbon::parse('2026-11-01 00:05:00', 'Africa/Nairobi'));

    expect($touched)->toBe(1)
        ->and(($this->touchedAt)($shop, $expiredToday))->not->toBe('2026-01-01 00:00:00')
        ->and(($this->touchedAt)($shop, $expiredBefore))->toBe('2026-01-01 00:00:00')
        ->and(($this->touchedAt)($shop, $notYet))->toBe('2026-01-01 00:00:00')
        ->and(($this->touchedAt)($shop, $noExpiry))->toBe('2026-01-01 00:00:00');
});

it('catches up a missed midnight on the next run, and touches a row once for two runs in a row', function () {
    $shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi', 'expiry_touched_at' => Carbon::parse('2026-10-30 12:00:00', 'Africa/Nairobi')]);
    $product = ($this->stocked)($shop, '2026-10-31');

    // The scheduler was down across the midnight; the first run after it still touches the row.
    $first = ($this->touch)(Carbon::parse('2026-11-02 09:00:00', 'Africa/Nairobi'));
    $stamp = ($this->touchedAt)($shop, $product);
    DB::table('shop_variation')->where('shop_id', $shop->id)->update(['updated_at' => '2026-01-01 00:00:00']);
    $second = ($this->touch)(Carbon::parse('2026-11-02 09:15:00', 'Africa/Nairobi'));

    expect($first)->toBe(1)
        ->and($stamp)->not->toBe('2026-01-01 00:00:00')
        ->and($second)->toBe(0)
        ->and(($this->touchedAt)($shop, $product))->toBe('2026-01-01 00:00:00');
});

it('touches a shop at its own midnight, not Nairobi\'s', function () {
    $karachi = Shop::factory()->create(['timezone' => 'Asia/Karachi', 'expiry_touched_at' => Carbon::parse('2026-10-31 12:00:00', 'UTC')]);
    $product = ($this->stocked)($karachi, '2026-10-31');

    // 2026-11-01 00:00 Karachi is 19:00 UTC on the 31st; it is 22:00 in Nairobi, still the 31st there.
    $before = ($this->touch)(Carbon::parse('2026-10-31 18:59:00', 'UTC'));
    $after = ($this->touch)(Carbon::parse('2026-10-31 19:01:00', 'UTC'));

    expect($before)->toBe(0)
        ->and($after)->toBe(1)
        ->and(($this->touchedAt)($karachi, $product))->not->toBe('2026-01-01 00:00:00');
});

it('touches every expired row once for a shop that has never run', function () {
    $shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi', 'expiry_touched_at' => null]);
    ($this->stocked)($shop, '2026-01-10');
    ($this->stocked)($shop, '2026-02-10');
    ($this->stocked)($shop, '2027-01-10');

    $first = ($this->touch)(Carbon::parse('2026-11-01 10:00:00', 'Africa/Nairobi'));
    $second = ($this->touch)(Carbon::parse('2026-11-01 10:15:00', 'Africa/Nairobi'));

    expect($first)->toBe(2)
        ->and($second)->toBe(0)
        ->and(Shop::query()->find($shop->id)->expiry_touched_at)->not->toBeNull();
});

it('ignores a batch with nothing left, and asks for the stores to be pushed what is touched', function () {
    $shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi', 'expiry_touched_at' => Carbon::parse('2026-10-31 12:00:00', 'Africa/Nairobi')]);
    $empty = ($this->stocked)($shop, '2026-10-31', 4);
    $this->ledger->consume($shop->id, $empty->defaultVariation, 4, StockMovementType::SALE, $empty, []);
    $held = ($this->stocked)($shop, '2026-10-31');

    $touched = ($this->touch)(Carbon::parse('2026-11-01 01:00:00', 'Africa/Nairobi'));

    expect($touched)->toBe(1);
    Bus::assertDispatched(PushShopStockJob::class, fn (PushShopStockJob $job) => $job->shopId === $shop->id && $job->productId === $held->id);
    Bus::assertNotDispatched(PushShopStockJob::class, fn (PushShopStockJob $job) => $job->productId === $empty->id);
});

it('is run by the scheduler every fifteen minutes, one run at a time', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'stock:touch-expiring'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
