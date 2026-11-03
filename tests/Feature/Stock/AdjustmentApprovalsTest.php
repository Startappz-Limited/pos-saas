<?php

use App\Actions\Business\RemoveFromBusiness;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\PurchaseReturn;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\Approvals\ApprovalRequested;
use App\Notifications\Approvals\ApprovalSettled;
use App\Services\Stock\StockLedger;
use App\Support\Stock\AdjustmentApproval;
use App\Support\Stock\LegacyAdjustmentSigns;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/*
 * Approving stock adjustments under the shared rule (decided 3 November 2026,
 * stage 10, step 1g follow-up): approvers are whoever holds the approve
 * permission in the business and may work in the shop, never the creator
 * (an owner nobody else could relieve excepted, with a note); they are
 * notified, the first to settle wins, the creator hears the outcome, and a
 * request pending for 24 hours is reminded about once.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-03 10:00:00'));
    $this->ledger = app(StockLedger::class);
    $this->shop = Shop::factory()->create(['code' => 'NRB']);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'cost_price' => null, 'stock_quantity' => 0]);
    $this->default = $this->product->defaultVariation;
    $this->member = function (string $role, ?Shop $shop = null): User {
        $user = User::factory()->create();
        $user->assignRole($role === 'owner' ? ownerRole() : $role);
        $user->shops()->attach($shop ?? $this->shop);

        return $user;
    };
    $this->owner = ($this->member)('owner');
    $this->clerk = ($this->member)('inventory-clerk');
    $this->manager = ($this->member)('manager');
    $this->ledger->receive($this->shop->id, $this->default, 100, 100, StockMovementType::INTAKE, ['received_at' => now()->subDay()]);
    $this->adjust = function (User $user, string $reason, int $change) {
        $this->actingAs($user)->post(route('stock-adjustments.store'), [
            'shop_id' => $this->shop->id, 'reason' => $reason, 'items' => [['product_id' => $this->product->id, 'quantity_change' => $change]],
        ])->assertSessionHasNoErrors();

        return StockAdjustment::query()->latest('id')->firstOrFail();
    };
    $this->requests = fn (User $user) => $user->notifications()->where('type', ApprovalRequested::class)->get();
    $this->quantity = fn (): int => $this->ledger->quantity($this->shop->id, $this->default->id);
});

describe('who is told', function () {
    it('notifies every eligible approver but the creator, in the app and by email', function () {
        Notification::fake();
        $elsewhere = ($this->member)('manager', Shop::factory()->create());
        $removed = ($this->member)('manager');
        app(RemoveFromBusiness::class)->execute($removed);

        $adjustment = ($this->adjust)($this->clerk, 'damaged', -2);

        Notification::assertSentTo([$this->manager, $this->owner], ApprovalRequested::class,
            fn (ApprovalRequested $sent, array $channels): bool => $sent->subject->uuid === $adjustment->uuid && $channels === ['database', 'mail']);
        Notification::assertNotSentTo([$this->clerk, $elsewhere, $removed], ApprovalRequested::class);
        expect(new ApprovalRequested(app(AdjustmentApproval::class)->subject($adjustment)))->toBeInstanceOf(ShouldQueue::class);
    });

    it('lets the first approver settle it, marks the others\' notifications resolved, and tells the creator', function () {
        $adjustment = ($this->adjust)($this->clerk, 'lost', -2);
        expect(($this->requests)($this->owner))->toHaveCount(1)
            ->and(($this->requests)($this->manager))->toHaveCount(1);

        $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $adjustment))->assertRedirect();
        $this->actingAs($this->owner)->post(route('stock-adjustments.approve', $adjustment))->assertForbidden();
        $this->actingAs($this->owner)->post(route('stock-adjustments.reject', $adjustment), ['rejection_reason' => 'Too late'])->assertForbidden();

        $ownerNotice = ($this->requests)($this->owner)->first();
        expect(($this->quantity)())->toBe(98)
            ->and($ownerNotice->data['resolved'])->toBe('approved')
            ->and($ownerNotice->read_at)->not->toBeNull();
        $outcome = $this->clerk->notifications()->where('type', ApprovalSettled::class)->sole();
        expect($outcome->data['outcome'])->toBe('approved')
            ->and($outcome->data['settled_by'])->toBe($this->manager->name);
    });

    it('needs a reason to reject, and tells the creator the reason', function () {
        $adjustment = ($this->adjust)($this->clerk, 'expired', -1);

        $this->actingAs($this->manager)->post(route('stock-adjustments.reject', $adjustment), [])->assertSessionHasErrors('rejection_reason');
        $this->actingAs($this->manager)->post(route('stock-adjustments.reject', $adjustment), ['rejection_reason' => 'Counted wrong'])->assertRedirect();

        $outcome = $this->clerk->notifications()->where('type', ApprovalSettled::class)->sole();
        expect($adjustment->fresh()->status)->toBe('rejected')
            ->and($outcome->data['outcome'])->toBe('rejected')
            ->and($outcome->data['note'])->toBe('Counted wrong')
            ->and(($this->quantity)())->toBe(100);
    });

    it('reminds approvers once after 24 hours, and lists it on the owner\'s dashboard', function () {
        $adjustment = ($this->adjust)($this->clerk, 'stolen', -1);

        $this->travel(23)->hours();
        $this->artisan('approvals:remind')->assertSuccessful();
        expect(($this->requests)($this->manager))->toHaveCount(1);

        $this->travel(2)->hours();
        $this->artisan('approvals:remind')->assertSuccessful();
        $this->artisan('approvals:remind')->assertSuccessful();

        expect(($this->requests)($this->manager)->where('data.reminder', true))->toHaveCount(1)
            ->and(($this->requests)($this->owner)->where('data.reminder', true))->toHaveCount(1)
            ->and($this->clerk->notifications()->count())->toBe(0)
            ->and($adjustment->fresh()->status)->toBe('pending');
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee($adjustment->adjustment_number);
    });

    it('shows the current business\'s notifications under the bell and opens them', function () {
        $adjustment = ($this->adjust)($this->clerk, 'damaged', -1);
        $notice = ($this->requests)($this->manager)->first();

        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk()->assertSee('Approval needed: Stock adjustment '.$adjustment->adjustment_number);
        $this->actingAs($this->manager)->get(route('notifications.open', $notice->id))->assertRedirect(route('stock-adjustments.show', $adjustment, false));
        expect($notice->fresh()->read_at)->not->toBeNull();
        $this->actingAs($this->clerk)->get(route('notifications.open', $notice->id))->assertNotFound();
    });
});

describe('own requests', function () {
    it('lets an owner nobody else could relieve approve their own, with a note, flagged self-approved', function () {
        $this->manager->shops()->detach();
        $adjustment = ($this->adjust)($this->owner, 'damaged', -1);

        $this->actingAs($this->owner)->post(route('stock-adjustments.approve', $adjustment), [])->assertSessionHasErrors('approval_note');
        $this->actingAs($this->owner)->post(route('stock-adjustments.approve', $adjustment), ['approval_note' => 'Only one here today'])->assertRedirect();

        $adjustment->refresh();
        expect($adjustment->status)->toBe('completed')
            ->and($adjustment->self_approved)->toBeTrue()
            ->and($adjustment->approval_note)->toBe('Only one here today')
            ->and(($this->quantity)())->toBe(99);
        $this->actingAs($this->owner)->get(route('stock-adjustments.index', ['self_approved' => 1]))->assertOk()->assertSee($adjustment->adjustment_number)->assertSee('Self-approved');
    });

    it('never lets an owner approve their own while someone else can, nor anyone else approve theirs', function () {
        $byOwner = ($this->adjust)($this->owner, 'damaged', -1);
        $byManager = ($this->adjust)($this->manager, 'damaged', -1);

        $this->actingAs($this->owner)->post(route('stock-adjustments.approve', $byOwner), ['approval_note' => 'x'])->assertForbidden();
        $this->actingAs($this->manager)->post(route('stock-adjustments.approve', $byManager), ['approval_note' => 'x'])->assertForbidden();
        expect(($this->quantity)())->toBe(100);
    });

    it('keeps a removed creator\'s pending adjustment for the owner to settle, and never applies it by itself', function () {
        $adjustment = ($this->adjust)($this->clerk, 'damaged', -3);
        app(RemoveFromBusiness::class)->execute($this->clerk);

        $this->travel(2)->days();
        $this->artisan('approvals:remind')->assertSuccessful();
        expect($adjustment->fresh()->status)->toBe('pending')
            ->and(($this->quantity)())->toBe(100);

        $this->actingAs($this->owner)->get(route('stock-adjustments.index'))->assertSee($adjustment->adjustment_number);
        $this->actingAs($this->owner)->post(route('stock-adjustments.approve', $adjustment))->assertRedirect();
        expect(($this->quantity)())->toBe(97);
    });
});

describe('the threshold', function () {
    it('counts what one person auto-applied at one shop on one day together, so splitting doesn\'t avoid a second person', function () {
        expect(($this->adjust)($this->manager, 'found', 30)->status)->toBe('completed')   // KES 3,000
            ->and(($this->adjust)($this->manager, 'found', 30)->status)->toBe('pending');  // 6,000 today

        // Another person, or the next day in the shop's timezone, starts again.
        expect(($this->adjust)($this->owner, 'found', 30)->status)->toBe('completed');
        $this->travelTo(Carbon::parse('2026-11-04 00:01:00', 'Africa/Nairobi'));
        expect(($this->adjust)($this->manager, 'found', 30)->status)->toBe('completed');
    });

    it('values mixed lines gross, never netting them', function () {
        [$red, $blue] = [$this->default, ProductVariation::factory()->create(['product_id' => $this->product->id, 'is_default' => false, 'stock_quantity' => 0])];
        $this->ledger->receive($this->shop->id, $blue, 100, 100, StockMovementType::INTAKE, ['received_at' => now()->subDay()]);
        $this->product->update(['has_variations' => true]);

        $this->actingAs($this->manager)->post(route('stock-adjustments.store'), [
            'shop_id' => $this->shop->id, 'reason' => 'other', 'items' => [
                ['product_id' => $this->product->id, 'variation_id' => $blue->id, 'quantity_change' => 30],
                ['product_id' => $this->product->id, 'variation_id' => $blue->id, 'quantity_change' => -30],
            ],
        ])->assertSessionHasNoErrors();

        expect(StockAdjustment::query()->latest('id')->first()->status)->toBe('pending'); // 6,000 gross, 0 net
    });
});

describe('history', function () {
    it('cancels instead of deleting, keeping the number, and refuses changes to settled adjustments', function () {
        $pending = ($this->adjust)($this->clerk, 'damaged', -1);
        $this->actingAs($this->clerk)->post(route('stock-adjustments.cancel', $pending))->assertRedirect();
        expect($pending->fresh()->status)->toBe('cancelled')
            ->and(StockAdjustment::whereKey($pending->id)->exists())->toBeTrue()
            ->and(($this->requests)($this->manager)->first()->data['resolved'])->toBe('cancelled')
            ->and(($this->adjust)($this->clerk, 'damaged', -1)->adjustment_number)->toBe('ADJ-NRB-2026-000002');
        $this->actingAs($this->owner)->delete("/stock-adjustments/{$pending->uuid}")->assertStatus(405);

        $applied = ($this->adjust)($this->manager, 'found', 1);
        expect($applied->status)->toBe('completed');
        $this->actingAs($this->owner)->get(route('stock-adjustments.edit', $applied))->assertForbidden();
        $this->actingAs($this->owner)->put(route('stock-adjustments.update', $applied), ['reason' => 'found', 'items' => [['product_id' => $this->product->id, 'quantity_change' => 5]]])->assertForbidden();
        $this->actingAs($this->owner)->post(route('stock-adjustments.cancel', $applied))->assertForbidden();
        expect($applied->fresh()->items->first()->quantity_change)->toBe(1);
    });

    it('still shows an old "returned to supplier" adjustment, and points an unapplied one at purchase returns', function () {
        $make = fn (string $status) => tap(StockAdjustment::factory()->create([
            'shop_id' => $this->shop->id, 'type' => 'decrease', 'reason' => 'returned_to_supplier', 'status' => $status, 'created_by' => $this->clerk->id,
        ]), fn (StockAdjustment $adjustment) => $adjustment->items()->create([
            'product_id' => $this->product->id, 'variation_id' => $this->default->id, 'shop_id' => $this->shop->id, 'quantity_change' => -2,
            'quantity_before' => 10, 'quantity_after' => 8,
        ]));
        $applied = $make('completed');
        $unapplied = $make('approved');

        $this->actingAs($this->owner)->get(route('stock-adjustments.show', $applied))->assertOk()->assertSee('Returned to Supplier')->assertSee('−2', false);
        $this->actingAs($this->owner)->get(route('stock-adjustments.index'))->assertOk()->assertSee($applied->adjustment_number);
        Artisan::call('stock-adjustments:list-unapplied', ['--business' => testBusiness()->id]);
        expect(Artisan::output())->toContain($unapplied->adjustment_number)
            ->toContain('Raise a purchase return for it, then reject this')
            ->not->toContain($applied->adjustment_number);
    });

    it('negates only positive lines of decrease adjustments, and changes nothing the second time', function () {
        $adjustment = fn (string $type) => StockAdjustment::factory()->create(['shop_id' => $this->shop->id, 'type' => $type, 'reason' => 'other', 'status' => 'completed', 'created_by' => $this->clerk->id]);
        $line = fn (StockAdjustment $adjustment, int $change) => $adjustment->items()->create(['product_id' => $this->product->id, 'variation_id' => $this->default->id, 'shop_id' => $this->shop->id, 'quantity_change' => $change]);
        $old = $line($adjustment('decrease'), 4);       // saved by the old form
        $imported = $line($adjustment('decrease'), -3); // already signed
        $increase = $line($adjustment('increase'), 5);

        expect(app(LegacyAdjustmentSigns::class)->fix())->toBe(1)
            ->and(app(LegacyAdjustmentSigns::class)->fix())->toBe(0)
            ->and([$old->fresh()->quantity_change, $imported->fresh()->quantity_change, $increase->fresh()->quantity_change])->toBe([-4, -3, 5]);
    });
});

it('records a purchase return with no purchase order, for stock bought before the system or for cash', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->owner)->post(route('purchase-returns.store'), [
        'supplier_id' => $supplier->id, 'shop_id' => $this->shop->id, 'reason' => 'defective',
        'items' => [['product_id' => $this->product->id, 'quantity' => 2, 'unit_cost' => 100]],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $return = PurchaseReturn::query()->latest('id')->firstOrFail();
    expect($return->purchase_order_id)->toBeNull()
        ->and($return->items()->sole()->stock_intake_id)->toBeNull();
    expect(DB::table('purchase_return_items')->where('purchase_return_id', $return->id)->value('quantity'))->toBe(2);
});
