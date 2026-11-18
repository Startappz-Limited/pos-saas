<?php

use App\Enums\AuditEvent;
use App\Enums\QuarantineStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\CostAllocation;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockRelease;
use App\Models\User;
use App\Services\Stock\QuarantineService;
use App\Services\Stock\StockLedger;
use App\Support\Stock\QuarantinePolicy;
use App\Support\Stock\SellableStock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/*
 * Quarantine and release (stage 11, quarantine policy). Returned stock whose batch or expiry cannot be established,
 * and known-expired medical stock, is held out of sale. Non-medical quarantined stock is released by an authorized
 * user, with the approver, reason, quantity, batch and expiry recorded; medical stock must be verified first and
 * known-expired medical stock is never released. Releases keep the original batch and expiry, are idempotent on a
 * request key, and never change on-hand stock.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2027-05-01 10:00:00', 'Africa/Nairobi'));
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->clerk = assignToNewShops(User::factory()->create());
    $this->clerk->assignRole(sharedRole('inventory-clerk'));
    $this->ledger = app(StockLedger::class);
    $this->service = app(QuarantineService::class);
    migrateAuditDatabase();

    $this->product = function (bool $medical): Product {
        $product = Product::factory()->create(['shop_id' => $this->shop->id, 'stock_quantity' => 0, 'has_variations' => false, 'unit' => 'piece', 'is_medical' => $medical]);
        $product->defaultVariation->update(['track_batches' => true]);

        return $product->fresh();
    };
    $this->stock = function (Product $product, int $quantity, array $context = []): CostLayer {
        $this->ledger->receive($this->shop->id, $product->defaultVariation, $quantity, 5.0, StockMovementType::RETURN, $context);

        return CostLayer::query()->where('variation_id', $product->defaultVariation->id)->latest('id')->firstOrFail();
    };
});

function expiredQuarantine(string $batch = 'E1'): array
{
    return ['batch_number' => $batch, 'expiry_date' => '2027-01-01', 'quarantine_status' => QuarantineStatus::EXPIRED->value, 'quarantine_reason' => 'Returned expired.'];
}

function unverifiedQuarantine(): array
{
    return ['quarantine_status' => QuarantineStatus::UNVERIFIED->value, 'quarantine_reason' => 'Batch not established.'];
}

// Classification (QuarantinePolicy): which returned stock is quarantined, and why.

it('quarantines medical stock that is known expired, and stock whose batch or expiry is unknown', function () {
    $boundary = '2027-05-01';

    expect(QuarantinePolicy::classify('1', 'B', '2027-01-01', true, true, $boundary, 'R1')['quarantine'])->toBe(QuarantineStatus::EXPIRED)
        ->and(QuarantinePolicy::classify('1', 'B', '2027-01-01', true, false, $boundary, 'R1')['quarantine'])->toBe(QuarantineStatus::EXPIRED)
        ->and(QuarantinePolicy::classify('1', 'B', null, true, true, $boundary, 'R1')['quarantine'])->toBe(QuarantineStatus::UNVERIFIED)
        ->and(QuarantinePolicy::classify('1', 'B', null, true, false, $boundary, 'R1')['quarantine'])->toBe(QuarantineStatus::UNVERIFIED)
        ->and(QuarantinePolicy::classify('1', null, '2028-01-01', true, false, $boundary, 'R1')['quarantine'])->toBe(QuarantineStatus::UNVERIFIED);
});

it('does not quarantine known, unexpired stock, nor stock that has no expiry to judge', function () {
    $boundary = '2027-05-01';

    expect(QuarantinePolicy::classify('1', 'B', '2028-01-01', true, true, $boundary, 'R1')['quarantine'])->toBeNull()
        ->and(QuarantinePolicy::classify('1', 'B', '2028-01-01', true, false, $boundary, 'R1')['quarantine'])->toBeNull()
        ->and(QuarantinePolicy::classify('1', null, null, false, false, $boundary, 'R1')['quarantine'])->toBeNull();
});

it('classifies a non-medical product\'s known expired stock as quarantined too, so it can be released with approval', function () {
    $boundary = '2027-05-01';

    $classified = QuarantinePolicy::classify('2', 'E1', '2027-01-01', true, false, $boundary, 'R9');

    expect($classified['quarantine'])->toBe(QuarantineStatus::EXPIRED)
        ->and($classified['reason'])->toContain('R9');
});

// Sellable stock and sales respect quarantine.

it('keeps quarantined stock out of sellable stock, and out of sales, but still on hand', function () {
    $product = ($this->product)(false);
    ($this->stock)($product, 4, ['batch_number' => 'OK', 'expiry_date' => '2028-01-01']);
    ($this->stock)($product, 6, ['batch_number' => 'U1'] + unverifiedQuarantine());

    expect($this->ledger->exactQuantity($this->shop->id, $product->defaultVariation->id))->toBe('10.000')
        ->and($this->ledger->sellableQuantity($this->shop->id, $product->defaultVariation->id))->toBe('4.000')
        ->and($this->ledger->quarantinedQuantity($this->shop->id, $product->defaultVariation->id))->toBe('6.000')
        ->and(SellableStock::sellableOf($this->shop, $product->defaultVariation->id))->toBe('4.000');

    // A sale of 5 is refused at the till: only 4 may be sold.
    expect(fn () => $this->ledger->consume($this->shop->id, $product->defaultVariation, 5, StockMovementType::SALE, $product->defaultVariation, ['enforce_stock' => true, 'exclude_expired' => true]))
        ->toThrow(BusinessRuleException::class);
});

it('never takes quarantined stock for a sale, even when nothing sellable is left', function () {
    $product = ($this->product)(false);
    $quarantined = ($this->stock)($product, 3, ['batch_number' => 'U1'] + unverifiedQuarantine());

    $this->ledger->consume($this->shop->id, $product->defaultVariation, 2, StockMovementType::SALE, $product->defaultVariation, ['exclude_expired' => true]);

    expect($quarantined->fresh()->remaining_quantity)->toBe('3.000');
});

it('takes quarantined stock only for a disposal that names its batch', function () {
    $product = ($this->product)(false);
    $quarantined = ($this->stock)($product, 3, ['batch_number' => 'U1'] + unverifiedQuarantine());

    $this->ledger->consume($this->shop->id, $product->defaultVariation, 2, StockMovementType::ADJUSTMENT_REMOVE, $product->defaultVariation, [
        'exact_layer' => true, 'include_quarantine' => true,
    ], $quarantined->id);

    expect($quarantined->fresh()->remaining_quantity)->toBe('1.000');
});

// Verification (medical stock with unknown expiry).

it('refuses to release medical stock until its batch and expiry are verified, then holds it if it is not expired', function () {
    $medical = ($this->product)(true);
    $layer = ($this->stock)($medical, 4, ['batch_number' => 'M1'] + unverifiedQuarantine());

    expect(fn () => $this->service->release($layer, $this->owner, '4', 'Looks fine', (string) Str::uuid()))
        ->toThrow(BusinessRuleException::class, 'verified');

    $verified = $this->service->verify($layer, $this->owner, 'M1-V', '2028-06-01');

    expect($verified->quarantine_status)->toBe(QuarantineStatus::HELD)
        ->and($verified->expiry_date->toDateString())->toBe('2028-06-01')
        ->and($verified->quarantine_verified_by)->toBe($this->owner->id);

    [$release] = $this->service->release($verified, $this->owner, '4', 'Verified batch, in date.', (string) Str::uuid());
    expect((string) $release->quantity)->toBe('4.000');
});

it('turns medical stock verified as expired into known-expired stock, which is never released', function () {
    $medical = ($this->product)(true);
    $layer = ($this->stock)($medical, 2, ['batch_number' => 'M2'] + unverifiedQuarantine());

    $verified = $this->service->verify($layer, $this->owner, 'M2', '2027-01-01');

    expect($verified->quarantine_status)->toBe(QuarantineStatus::EXPIRED)
        ->and(fn () => $this->service->release($verified, $this->owner, '2', 'Please', (string) Str::uuid()))
        ->toThrow(BusinessRuleException::class, 'Expired medical stock cannot be released');
    expect(StockRelease::query()->count())->toBe(0);
});

// Release: both product classifications, known and unknown expiry.

it('never releases known-expired medical stock', function () {
    $medical = ($this->product)(true);
    $layer = ($this->stock)($medical, 3, expiredQuarantine('ME'));

    expect(fn () => $this->service->release($layer, $this->owner, '3', 'Sell anyway', (string) Str::uuid()))
        ->toThrow(BusinessRuleException::class, 'Dispose of it or return it to the supplier');
    expect($layer->fresh()->remaining_quantity)->toBe('3.000')
        ->and(StockRelease::query()->count())->toBe(0);
});

it('releases known-expired non-medical stock with its expiry preserved, and makes it sellable', function () {
    $product = ($this->product)(false);
    $layer = ($this->stock)($product, 3, expiredQuarantine('NE'));
    expect($this->ledger->sellableQuantity($this->shop->id, $product->defaultVariation->id))->toBe('0.000');

    [$release] = $this->service->release($layer, $this->owner, '3', 'Date was keyed in wrongly; pack is in date.', (string) Str::uuid());

    $released = $release->toLayer;
    expect($released->batch_number)->toBe('NE')
        ->and($released->expiry_date->toDateString())->toBe('2027-01-01')
        ->and($released->stock_release_id)->toBe($release->id)
        ->and($released->isQuarantined())->toBeFalse()
        ->and($this->ledger->sellableQuantity($this->shop->id, $product->defaultVariation->id))->toBe('3.000')
        ->and($this->ledger->exactQuantity($this->shop->id, $product->defaultVariation->id))->toBe('3.000')
        ->and($this->ledger->quarantinedQuantity($this->shop->id, $product->defaultVariation->id))->toBe('0.000');
});

it('releases unknown-expiry or unknown-batch non-medical stock, recording that they were unknown', function () {
    $product = ($this->product)(false);
    $layer = ($this->stock)($product, 2, unverifiedQuarantine());

    [$release] = $this->service->release($layer, $this->owner, '2', 'Counted and checked by manager.', (string) Str::uuid());

    expect($release->batch_number)->toBeNull()
        ->and($release->expiry_date)->toBeNull()
        ->and($release->quarantine_status)->toBe(QuarantineStatus::UNVERIFIED)
        ->and($release->approved_by)->toBe($this->owner->id)
        ->and($release->reason)->toBe('Counted and checked by manager.')
        ->and($release->approved_at)->not->toBeNull()
        ->and($release->toLayer->batch_number)->toBeNull();
});

// Partial releases, retries, and repeated attempts.

it('releases part of a quarantined layer, and refuses more than remains', function () {
    $product = ($this->product)(false);
    $layer = ($this->stock)($product, 10, expiredQuarantine('PART'));

    $this->service->release($layer, $this->owner, '3', 'First batch of three.', (string) Str::uuid());
    $this->service->release($layer, $this->owner, '4', 'Next four.', (string) Str::uuid());

    expect($layer->fresh()->remaining_quantity)->toBe('3.000')
        ->and(StockRelease::query()->count())->toBe(2)
        ->and(fn () => $this->service->release($layer, $this->owner, '5', 'Too many.', (string) Str::uuid()))
        ->toThrow(BusinessRuleException::class, 'Only 3.000 left in quarantine');
});

it('answers a retried release with the release it already made, and moves nothing again', function () {
    $product = ($this->product)(false);
    $layer = ($this->stock)($product, 5, expiredQuarantine('RETRY'));
    $key = (string) Str::uuid();

    [$first, $firstReplayed] = $this->service->release($layer, $this->owner, '2', 'Release two.', $key);
    [$second, $secondReplayed] = $this->service->release($layer, $this->owner, '2', 'Release two.', $key);

    expect($firstReplayed)->toBeFalse()
        ->and($secondReplayed)->toBeTrue()
        ->and($second->id)->toBe($first->id)
        ->and(StockRelease::query()->count())->toBe(1)
        ->and(CostLayer::query()->where('stock_release_id', $first->id)->count())->toBe(1)
        ->and($layer->fresh()->remaining_quantity)->toBe('3.000')
        ->and($this->ledger->sellableQuantity($this->shop->id, $product->defaultVariation->id))->toBe('2.000');
});

it('does not let a second, different request release what the first already took', function () {
    $product = ($this->product)(false);
    $layer = ($this->stock)($product, 3, expiredQuarantine('DOUBLE'));

    $this->service->release($layer, $this->owner, '3', 'All three.', (string) Str::uuid());

    expect(fn () => $this->service->release($layer, $this->owner, '3', 'All three again.', (string) Str::uuid()))
        ->toThrow(BusinessRuleException::class);
    expect($this->ledger->sellableQuantity($this->shop->id, $product->defaultVariation->id))->toBe('3.000')
        ->and(StockRelease::query()->count())->toBe(1);
});

it('never changes on-hand stock when releasing, only where it sits', function () {
    $product = ($this->product)(false);
    $layer = ($this->stock)($product, 4, expiredQuarantine('ONHAND'));
    $before = $this->ledger->exactQuantity($this->shop->id, $product->defaultVariation->id);

    $this->service->release($layer, $this->owner, '4', 'Released.', (string) Str::uuid());

    expect($this->ledger->exactQuantity($this->shop->id, $product->defaultVariation->id))->toBe($before);
});

// Authorization and audit.

it('refuses a release or verification to someone without stock.release-quarantine', function () {
    $product = ($this->product)(false);
    $layer = ($this->stock)($product, 2, expiredQuarantine('AUTH'));

    expect(fn () => $this->service->release($layer, $this->clerk, '2', 'Let me.', (string) Str::uuid()))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->verify($layer, $this->clerk, 'X', '2028-01-01'))
        ->toThrow(AuthorizationException::class);
    expect($layer->fresh()->remaining_quantity)->toBe('2.000');
});

it('audits each release, and each verification', function () {
    $medical = ($this->product)(true);
    $layer = ($this->stock)($medical, 2, unverifiedQuarantine());
    $this->service->verify($layer, $this->owner, 'AUD', '2028-01-01');

    $product = ($this->product)(false);
    $release = ($this->stock)($product, 2, expiredQuarantine('AUD2'));
    $this->service->release($release, $this->owner, '2', 'Audited release.', (string) Str::uuid());

    expect(AuditLog::query()->where('auditable_type', StockRelease::class)->where('event', AuditEvent::CREATED->value)->count())->toBe(1)
        ->and(AuditLog::query()->where('auditable_type', CostLayer::class)->count())->toBeGreaterThanOrEqual(2);
});

// The screen and its routes.

it('lets the owner release through the screen, and turns away a clerk without the permission', function () {
    $product = ($this->product)(false);
    $layer = ($this->stock)($product, 2, expiredQuarantine('HTTP'));
    $key = (string) Str::uuid();

    $this->actingAs($this->owner)->get(route('stock-quarantine.index'))->assertOk()->assertSee('HTTP');

    $this->actingAs($this->clerk)->post(route('stock-quarantine.release', $layer), [
        'quantity' => 2, 'reason' => 'Let me.', 'request_key' => $key,
    ])->assertForbidden();

    $this->actingAs($this->owner)->post(route('stock-quarantine.release', $layer), [
        'quantity' => 2, 'reason' => 'Checked.', 'request_key' => $key,
    ])->assertRedirect()->assertSessionHas('success');

    $this->actingAs($this->owner)->post(route('stock-quarantine.release', $layer), [
        'quantity' => 2, 'reason' => 'Checked.', 'request_key' => $key,
    ])->assertSessionHas('success', 'This release was already recorded.');

    expect(StockRelease::query()->count())->toBe(1);
});

it('shows medical known-expired stock on the screen without a release form', function () {
    $medical = ($this->product)(true);
    ($this->stock)($medical, 2, expiredQuarantine('SHOWN'));

    $this->actingAs($this->owner)->get(route('stock-quarantine.index'))
        ->assertOk()
        ->assertSee('Expired medical stock cannot be released for sale')
        ->assertDontSee('name="request_key"', false);
});

it('turns away a release of medical known-expired stock through the screen', function () {
    $medical = ($this->product)(true);
    $layer = ($this->stock)($medical, 2, expiredQuarantine('NOPE'));

    $this->actingAs($this->owner)->post(route('stock-quarantine.release', $layer), [
        'quantity' => 2, 'reason' => 'Please.', 'request_key' => (string) Str::uuid(),
    ])->assertSessionHas('error');

    expect($layer->fresh()->remaining_quantity)->toBe('2.000');
});

// Review follow-up: medical expiry is judged now, released medical stock expires, availability, settlement, precision, keys.

it('refuses a medical release once stock verified in date has since expired', function () {
    $medical = ($this->product)(true);
    $layer = ($this->stock)($medical, 3, ['batch_number' => 'LATE'] + unverifiedQuarantine());
    $this->service->verify($layer, $this->owner, 'LATE', '2027-06-01');

    $this->travelTo(Carbon::parse('2027-07-01 10:00:00', 'Africa/Nairobi'));

    expect(fn () => $this->service->release($layer, $this->owner, '3', 'Still fine.', (string) Str::uuid()))
        ->toThrow(BusinessRuleException::class, 'Expired medical stock cannot be released');
    expect(StockRelease::query()->count())->toBe(0);
});

it('stops a released medical layer being sold once its expiry passes', function () {
    $medical = ($this->product)(true);
    $layer = ($this->stock)($medical, 2, ['batch_number' => 'MREL'] + unverifiedQuarantine());
    $verified = $this->service->verify($layer, $this->owner, 'MREL', '2027-06-01');
    $this->service->release($verified, $this->owner, '2', 'Verified and in date.', (string) Str::uuid());

    expect($this->ledger->sellableQuantity($this->shop->id, $medical->defaultVariation->id))->toBe('2.000');

    $this->travelTo(Carbon::parse('2027-07-01 10:00:00', 'Africa/Nairobi'));

    // The release exempts only non-medical stock from expiry: this medical batch is expired, so it is not sold.
    expect($this->ledger->sellableQuantity($this->shop->id, $medical->defaultVariation->id))->toBe('0.000')
        ->and(fn () => $this->ledger->consume($this->shop->id, $medical->defaultVariation, 1, StockMovementType::SALE, $medical, ['exclude_expired' => true]))
        ->toThrow(BusinessRuleException::class, 'Medical stock past its expiry cannot be sold');
});

it('refuses a medical sale that only expired stock could meet, whatever the caller', function () {
    $medical = ($this->product)(true);
    $expired = ($this->stock)($medical, 2, ['batch_number' => 'OLDM', 'expiry_date' => '2027-01-01']);

    expect(fn () => $this->ledger->consume($this->shop->id, $medical->defaultVariation, 1, StockMovementType::SALE, $medical, ['exclude_expired' => true]))
        ->toThrow(BusinessRuleException::class, 'Medical stock past its expiry cannot be sold');
    expect($expired->fresh()->remaining_quantity)->toBe('2.000');
});

it('refuses any sale that only expired stock could meet when the till enforces stock', function () {
    $product = ($this->product)(false);
    $expired = ($this->stock)($product, 4, ['batch_number' => 'OLDN', 'expiry_date' => '2027-01-01']);

    expect(fn () => $this->ledger->consume($this->shop->id, $product->defaultVariation, 1, StockMovementType::SALE, $product, [
        'enforce_stock' => true, 'exclude_expired' => true,
    ]))->toThrow(BusinessRuleException::class, 'Only 0.000 left');
    expect($expired->fresh()->remaining_quantity)->toBe('4.000');
});

it('settles a provisional sale still waiting for stock when quarantined stock is released', function () {
    $product = ($this->product)(false);
    $layer = ($this->stock)($product, 3, expiredQuarantine('SETTLE'));

    // Nothing is sellable, so a sale of 2 is taken provisionally and waits for stock.
    $this->ledger->consume($this->shop->id, $product->defaultVariation, 2, StockMovementType::SALE, $product, ['exclude_expired' => true]);
    expect(CostAllocation::query()->where('is_provisional', true)->count())->toBe(1);

    $this->service->release($layer, $this->owner, '3', 'Checked; in date.', (string) Str::uuid());

    // Only one unit is left after the sale: the released stock settles the provisional sale rather than reporting 3.
    expect(CostAllocation::query()->where('is_provisional', true)->count())->toBe(0)
        ->and($this->ledger->sellableQuantity($this->shop->id, $product->defaultVariation->id))->toBe('1.000')
        ->and($this->ledger->exactQuantity($this->shop->id, $product->defaultVariation->id))->toBe('1.000');
});

it('refuses a fraction of a whole-unit layer on release', function () {
    $whole = ($this->product)(false);
    $whole->defaultVariation->baseUnit()->update(['precision' => 0]);
    $layer = ($this->stock)($whole, 3, expiredQuarantine('WHOLE'));

    expect(fn () => $this->service->release($layer, $this->owner, '1.5', 'Half a pack.', (string) Str::uuid()))
        ->toThrow(BusinessRuleException::class, 'whole number');
    expect($layer->fresh()->remaining_quantity)->toBe('3.000');

    $this->service->release($layer, $this->owner, '1', 'One whole pack.', (string) Str::uuid());
    expect($layer->fresh()->remaining_quantity)->toBe('2.000');
});

it('refuses a request key reused for a different release, and answers a true retry', function () {
    $product = ($this->product)(false);
    $first = ($this->stock)($product, 4, expiredQuarantine('KEY1'));
    $second = ($this->stock)($product, 4, expiredQuarantine('KEY2'));
    $key = (string) Str::uuid();

    [$made] = $this->service->release($first, $this->owner, '1', 'First release.', $key);

    // The same key for another layer, another quantity, or another reason is a different release: refused.
    expect(fn () => $this->service->release($second, $this->owner, '1', 'First release.', $key))
        ->toThrow(BusinessRuleException::class, 'already used for a different release');
    expect(fn () => $this->service->release($first, $this->owner, '2', 'First release.', $key))
        ->toThrow(BusinessRuleException::class, 'already used for a different release');
    expect(fn () => $this->service->release($first, $this->owner, '1', 'Another reason.', $key))
        ->toThrow(BusinessRuleException::class, 'already used for a different release');

    // The same release, retried, is answered with the release it made, and moves nothing.
    [$again, $replayed] = $this->service->release($first, $this->owner, '1', 'First release.', $key);
    expect($replayed)->toBeTrue()
        ->and($again->id)->toBe($made->id)
        ->and($first->fresh()->remaining_quantity)->toBe('3.000')
        ->and($second->fresh()->remaining_quantity)->toBe('4.000');
});
