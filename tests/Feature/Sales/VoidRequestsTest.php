<?php

use App\Models\CashRegister;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\Approvals\ApprovalRequested;
use App\Notifications\Approvals\ApprovalSettled;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

/*
 * Void requests (decided 3 November 2026, stage 10, step 2): someone without
 * sales.void asks, with a reason, inside the window; everyone eligible under
 * the shared approval rule is notified; the first approval voids the sale (the
 * window checked at the request's time, up to 48 hours later), a rejection
 * needs a reason, and the requester hears the outcome.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-02 10:00:00'));
    $this->shop = Shop::factory()->create();
    $this->member = function (string|Role $role, ?Shop $shop = null): User {
        $user = User::factory()->create();
        $user->assignRole($role === 'owner' ? ownerRole() : $role);
        $user->shops()->attach($shop ?? $this->shop);

        return $user;
    };
    $lead = app(TenantContext::class)->run(testBusiness(), fn () => Role::create(['name' => 'shift-lead', 'guard_name' => 'web', 'business_id' => testBusiness()->id]));
    $lead->givePermissionTo(['sales.view', 'sales.void']);
    $this->owner = ($this->member)('owner');
    $this->cashier = ($this->member)('cashier');
    $this->manager = ($this->member)('manager');     // no sales.void since this stage
    $this->lead = ($this->member)('shift-lead');
    $this->leadElsewhere = ($this->member)('shift-lead', Shop::factory()->create());
    $this->session = CashRegister::factory()->create(['shop_id' => $this->shop->id, 'user_id' => $this->owner->id]);
    $this->sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'register_id' => $this->session->id, 'status' => 'completed', 'completed_at' => now()]);
    $this->ask = fn (User $user, Sale $sale, string $reason = 'Rang it up twice') => $this->actingAs($user)->post(route('sales.void-requests.store', $sale), ['reason' => $reason]);
    $this->pending = fn (): ?SaleVoidRequest => SaleVoidRequest::query()->latest('id')->first();
});

it('lets a cashier ask, notifying everyone who can void in the shop but the cashier', function () {
    Notification::fake();

    ($this->ask)($this->cashier, $this->sale)->assertRedirect()->assertSessionHas('success');

    $request = ($this->pending)();
    expect($request->status)->toBe('pending')
        ->and($request->requested_by)->toBe($this->cashier->id);
    Notification::assertSentTo([$this->owner, $this->lead], ApprovalRequested::class, fn (ApprovalRequested $sent) => $sent->subject->uuid === $request->uuid);
    Notification::assertNotSentTo([$this->cashier, $this->manager, $this->leadElsewhere], ApprovalRequested::class);
});

it('voids the sale on the first approval, resolves the others\' notifications, and tells the requester', function () {
    ($this->ask)($this->cashier, $this->sale);
    $request = ($this->pending)();

    $this->actingAs($this->lead)->post(route('void-requests.approve', $request))->assertRedirect()->assertSessionHas('success');
    $this->actingAs($this->owner)->post(route('void-requests.approve', $request))->assertForbidden();

    expect($this->sale->fresh()->status)->toBe('voided')
        ->and($this->sale->fresh()->voided_by)->toBe($this->lead->id)
        ->and($this->sale->fresh()->void_reason)->toBe('Rang it up twice')
        ->and($request->fresh()->status)->toBe('approved')
        ->and($this->owner->notifications()->where('type', ApprovalRequested::class)->sole()->data['resolved'])->toBe('approved')
        ->and($this->cashier->notifications()->where('type', ApprovalSettled::class)->sole()->data['outcome'])->toBe('approved');
});

it('still voids a sale asked about in time when the approval comes after the window, up to 48 hours', function () {
    ($this->ask)($this->cashier, $this->sale);
    $this->session->update(['status' => 'closed', 'closed_at' => now()->addHour()]);

    $this->travel(5)->hours();
    $this->actingAs($this->owner)->post(route('void-requests.approve', ($this->pending)()))->assertSessionHas('success');
    expect($this->sale->fresh()->status)->toBe('voided');
});

it('refuses a request past the window with the time-limit message (422 on the flat API)', function () {
    $this->session->update(['status' => 'closed', 'closed_at' => now()->addHour()]);
    $this->travel(2)->hours();

    ($this->ask)($this->cashier, $this->sale)->assertSessionHas('error', "This sale's register session has closed, so it can't be voided. Use a return instead.");
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/sales/{$this->sale->getRouteKey()}/void-request", ['reason' => 'x'])->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', "This sale's register session has closed, so it can't be voided. Use a return instead.");
    expect(SaleVoidRequest::count())->toBe(0);
});

it('expires a request not approved within 48 hours, and tells the requester', function () {
    ($this->ask)($this->cashier, $this->sale);
    $request = ($this->pending)();

    $this->travel(49)->hours();
    $this->actingAs($this->owner)->post(route('void-requests.approve', $request))->assertSessionHas('error');
    expect($this->sale->fresh()->status)->toBe('completed');

    $this->artisan('approvals:remind')->assertSuccessful();
    expect($request->fresh()->status)->toBe('expired')
        ->and($this->cashier->notifications()->where('type', ApprovalSettled::class)->sole()->data['outcome'])->toBe('expired');
});

it('reminds approvers once after 24 hours and lists it on the owner\'s dashboard', function () {
    ($this->ask)($this->cashier, $this->sale);
    $this->travel(25)->hours();
    $this->artisan('approvals:remind')->assertSuccessful();
    $this->artisan('approvals:remind')->assertSuccessful();

    expect($this->lead->notifications()->where('type', ApprovalRequested::class)->get()->where('data.reminder', true))->toHaveCount(1);
    $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee('Void sale '.$this->sale->invoice_number);
});

it('needs a reason to reject, and tells the requester why', function () {
    ($this->ask)($this->cashier, $this->sale);
    $request = ($this->pending)();

    $this->actingAs($this->lead)->post(route('void-requests.reject', $request), [])->assertSessionHasErrors('rejection_reason');
    $this->actingAs($this->lead)->post(route('void-requests.reject', $request), ['rejection_reason' => 'It was paid'])->assertSessionHas('success');

    $outcome = $this->cashier->notifications()->where('type', ApprovalSettled::class)->sole();
    expect($request->fresh()->status)->toBe('rejected')
        ->and($outcome->data['note'])->toBe('It was paid')
        ->and($this->sale->fresh()->status)->toBe('completed');
});

it('sends holders of sales.void to void directly, refuses a second request, and lets the requester cancel', function () {
    ($this->ask)($this->lead, $this->sale)->assertForbidden();
    ($this->ask)($this->cashier, $this->sale);
    ($this->ask)(($this->member)('cashier'), $this->sale)->assertSessionHas('error', 'A void of this sale has already been requested.');

    $request = ($this->pending)();
    $this->actingAs($this->cashier)->post(route('void-requests.approve', $request))->assertForbidden();
    $this->actingAs($this->cashier)->post(route('void-requests.cancel', $request))->assertSessionHas('success');
    expect($request->fresh()->status)->toBe('cancelled');
});

it('works on the flat API: ask, list for the approver, approve', function () {
    Sanctum::actingAs($this->cashier, ['*']);
    $uuid = $this->postJson("/api/sales/{$this->sale->getRouteKey()}/void-request", ['reason' => 'Rang it up twice'])
        ->assertCreated()->assertJsonPath('success', true)->json('data.uuid');

    Sanctum::actingAs($this->lead, ['*']);
    expect($this->getJson('/api/void-requests')->assertOk()->json('data.0.uuid'))->toBe($uuid);
    $this->postJson("/api/void-requests/{$uuid}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    expect($this->sale->fresh()->status)->toBe('voided');

    Sanctum::actingAs($this->manager, ['*']);
    expect($this->getJson('/api/void-requests')->assertOk()->json('data'))->toBe([]);
});

it('shows the request on the sale page: Request void to a cashier, Approve to an approver', function () {
    $this->actingAs($this->cashier)->get(route('sales.show', $this->sale))->assertOk()->assertSee('Request void');
    ($this->ask)($this->cashier, $this->sale);

    $this->actingAs($this->lead)->get(route('sales.show', $this->sale))->assertOk()->assertSee('Approve and void')->assertSee('Rang it up twice');
    $this->actingAs($this->lead)->get(route('void-requests.index'))->assertOk()->assertSee($this->sale->invoice_number);
});

// TEMPORARY until step 4 refunds payments on a void (decided 3 November 2026): remove with the refusal.
it('refuses a void request for a pending sale that has payments', function () {
    $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'register_id' => $this->session->id, 'status' => 'pending', 'completed_at' => null, 'paid_amount' => 500]);

    ($this->ask)($this->cashier, $sale)->assertSessionHas('error', "This sale has payments. It can't be voided until refunds are supported.");
    Sanctum::actingAs($this->cashier, ['*']);
    $this->postJson("/api/sales/{$sale->getRouteKey()}/void-request", ['reason' => 'x'])->assertUnprocessable();
    expect(SaleVoidRequest::count())->toBe(0);
});
