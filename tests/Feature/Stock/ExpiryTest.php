<?php

use App\Models\Shop;
use App\Support\Stock\Expiry;
use Illuminate\Support\Carbon;

/*
 * Expiry (stage 11, step 5): a batch is sellable until the end of the shop's
 * local day of its expiry_date, expired from the start of the next one.
 */

it('is sellable at 23:59 on the expiry day and expired at 00:00 the next, Nairobi time', function () {
    $shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi']);

    $stillSellable = Carbon::parse('2026-10-31 23:59:59', 'Africa/Nairobi');
    $expired = Carbon::parse('2026-11-01 00:00:00', 'Africa/Nairobi');

    expect(Expiry::isExpired('2026-10-31', $shop, $stillSellable))->toBeFalse()
        ->and(Expiry::isExpired('2026-10-31', $shop, $expired))->toBeTrue();
});

it('gives the same answer for the same moment expressed in UTC', function () {
    $shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi']);

    // Nairobi is UTC+3: 2026-11-01 00:00 Nairobi is 2026-10-31 21:00 UTC.
    expect(Expiry::isExpired('2026-10-31', $shop, Carbon::parse('2026-10-31 21:00:00', 'UTC')))->toBeTrue()
        ->and(Expiry::isExpired('2026-10-31', $shop, Carbon::parse('2026-10-31 20:59:59', 'UTC')))->toBeFalse();
});

it('uses a shop\'s own timezone, not Nairobi\'s', function () {
    $shop = Shop::factory()->create(['timezone' => 'Asia/Karachi']); // UTC+5

    // 2026-11-01 00:00 Karachi is 2026-10-31 19:00 UTC.
    expect(Expiry::isExpired('2026-10-31', $shop, Carbon::parse('2026-10-31 19:00:00', 'UTC')))->toBeTrue()
        ->and(Expiry::isExpired('2026-10-31', $shop, Carbon::parse('2026-10-31 18:59:59', 'UTC')))->toBeFalse();
});

it('is never expired with no expiry date', function () {
    $shop = Shop::factory()->create();

    expect(Expiry::isExpired(null, $shop))->toBeFalse();
});

it('parses a month-only expiry as the month\'s last day, and a full date as given', function () {
    expect(Expiry::parse('10/2026'))->toBe('2026-10-31')
        ->and(Expiry::parse('2/2028'))->toBe('2028-02-29') // leap year
        ->and(Expiry::parse('2026-10-15'))->toBe('2026-10-15')
        ->and(Expiry::parse(null))->toBeNull()
        ->and(Expiry::parse(''))->toBeNull();
});

it('gives the expired-date range used by the touch command', function () {
    $shop = Shop::factory()->create(['timezone' => 'Africa/Nairobi']);
    $now = Carbon::parse('2026-11-02 08:00:00', 'Africa/Nairobi');

    // Never run before: no lower bound, upper is yesterday (the last fully-expired day).
    expect(Expiry::expiredDateRange($shop, null, $now))->toBe([null, '2026-11-01']);

    // Run yesterday at 08:00: only 1 Nov's batches are newly expired since then.
    $from = Carbon::parse('2026-11-01 08:00:00', 'Africa/Nairobi');
    expect(Expiry::expiredDateRange($shop, $from, $now))->toBe(['2026-10-31', '2026-11-01']);
});
