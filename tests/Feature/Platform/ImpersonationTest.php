<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Impersonation;
use App\Models\Role;
use App\Support\Platform\Impersonation as ImpersonationSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Tests\Support\TwoBusinesses;

/**
 * Impersonation, Phase 5 (B3): audited, time-limited, with a banner on every
 * page; while it lasts the operator has exactly the impersonated user's
 * permissions (no Gate::before, no console); operator rights return only when
 * it ends, by hand, at its time limit or on signing out.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->operator = platformOperator();
    $this->tenant = app(TenantContext::class);
    $this->tenant->forget();
    $this->b = $this->fixture->b->business;
    // B's cashier: the business's own copy of the cashier template.
    $this->cashier = $this->fixture->b->cashier;
    $this->tenant->run($this->b, fn () => Role::query()->where('business_id', $this->b->id)->where('name', 'cashier')->firstOrFail()
        ->syncPermissions(['products.view', 'dashboard.view']));
    $this->tenant->forget();
});

/**
 * Starts impersonating $user in $business as the operator.
 */
function impersonate($test, $user, Business $business, string $reason = 'Support ticket 42')
{
    app(TenantContext::class)->forget();

    return $test->actingAs($test->operator)->post(route('platform.businesses.impersonate', [$business, $user->uuid]), ['reason' => $reason]);
}

/**
 * @return array<int, string>
 */
function auditTags(string $action): array
{
    return AuditLog::query()->latest('id')->get()->first(fn ($row) => in_array($action, $row->tags ?? [], true))?->tags ?? [];
}

it('starts with a reason, logs it, and signs the operator in as the user in that business', function () {
    impersonate($this, $this->cashier, $this->b)->assertRedirect(route('dashboard'));

    $log = Impersonation::query()->sole();
    expect($log->operator_id)->toBe($this->operator->id)
        ->and($log->user_id)->toBe($this->cashier->id)
        ->and($log->business_id)->toBe($this->b->id)
        ->and($log->reason)->toBe('Support ticket 42')
        ->and($log->expires_at->diffInMinutes($log->started_at, true))->toEqual(30.0)
        ->and(Auth::id())->toBe($this->cashier->id)
        ->and(session(ImpersonationSession::OPERATOR_KEY))->toBe($this->operator->id)
        ->and(auditTags('impersonation.started'))->toContain('platform');

    $started = AuditLog::query()->latest('id')->get()->first(fn ($row) => in_array('impersonation.started', $row->tags ?? [], true));
    expect($started->user_id)->toBe($this->operator->id)->and($started->business_id)->toBe($this->b->id);
});

it('refuses without a reason', function () {
    impersonate($this, $this->cashier, $this->b, '')->assertSessionHasErrors('reason');

    expect(Impersonation::query()->count())->toBe(0)->and(Auth::id())->toBe($this->operator->id);
});

it('shows a banner on every page while it lasts', function () {
    impersonate($this, $this->cashier, $this->b);

    $this->get(route('dashboard'))->assertOk()
        ->assertSee('id="impersonation-banner"', false)
        ->assertSeeText('you are signed in as '.$this->cashier->name.' in Business B')
        ->assertSee(route('impersonation.end'), false);
    $this->get(route('products.index'))->assertOk()->assertSee('id="impersonation-banner"', false);
});

it('gives the operator exactly the user\'s permissions while impersonating', function () {
    impersonate($this, $this->cashier, $this->b);

    // What the cashier can do.
    $this->get(route('products.index'))->assertOk();
    // What the cashier can't: refused, Gate::before doesn't pass.
    $this->get(route('roles.index'))->assertForbidden();
    $this->get(route('users.create'))->assertForbidden();
    expect(Auth::user()->can('roles.view'))->toBeFalse()
        ->and(Auth::user()->can('anything.at-all'))->toBeFalse();
    // The console is closed until it ends.
    $this->get(route('platform.dashboard'))->assertNotFound();
    $this->get(route('platform.businesses.index'))->assertNotFound();
    // Only business B: the operator can't reach A through the user either.
    $this->get(route('customers.show', $this->fixture->a->customer))->assertNotFound();
});

it('marks what is done while impersonating with the operator', function () {
    impersonate($this, $this->fixture->b->owner, $this->b);

    $this->post(route('roles.store'), ['name' => 'night-shift'])->assertSessionHasNoErrors();

    $role = Role::withoutGlobalScopes()->where('name', 'night-shift')->firstOrFail();
    $audit = AuditLog::query()->where('auditable_type', Role::class)->where('auditable_id', $role->id)->where('event', 'created')->first();
    expect($audit->user_id)->toBe($this->fixture->b->owner->id)
        ->and($audit->tags)->toContain('impersonated_by:'.$this->operator->id)
        ->and($role->business_id)->toBe($this->b->id);
});

it('ends by hand: the operator is back and has the console again', function () {
    impersonate($this, $this->cashier, $this->b);

    $this->post(route('impersonation.end'))->assertRedirect(route('platform.dashboard'));

    $log = Impersonation::query()->sole();
    expect(Auth::id())->toBe($this->operator->id)
        ->and($log->ended_at)->not->toBeNull()
        ->and($log->end_reason)->toBe(Impersonation::ENDED_MANUALLY)
        ->and(session(ImpersonationSession::OPERATOR_KEY))->toBeNull()
        ->and(auditTags('impersonation.ended'))->toContain('platform');

    $this->get(route('platform.dashboard'))->assertOk()->assertDontSee('id="impersonation-banner"', false);
});

it('ends at its time limit on the next request', function () {
    impersonate($this, $this->cashier, $this->b);

    $this->travel(31)->minutes();
    $this->get(route('products.index'))->assertRedirect(route('platform.dashboard'));

    expect(Auth::id())->toBe($this->operator->id)
        ->and(Impersonation::query()->sole()->end_reason)->toBe(Impersonation::EXPIRED);
    $this->get(route('platform.dashboard'))->assertOk();
});

it('ends when the session signs out, closing the log', function () {
    impersonate($this, $this->cashier, $this->b);

    $this->post(route('logout'))->assertRedirect();

    $log = Impersonation::query()->sole();
    expect($log->end_reason)->toBe(Impersonation::SIGNED_OUT)
        ->and(Auth::check())->toBeFalse()
        ->and(auditTags('impersonation.ended'))->toContain('impersonated_by:'.$this->operator->id);
});

it('refuses an operator, someone outside the business, an inactive member and a suspended business', function () {
    $other = platformOperator('Other Ops');
    impersonate($this, $other, $this->b)->assertSessionHas('error', 'A platform operator can\'t be impersonated.');

    // A's owner is no member of B.
    impersonate($this, $this->fixture->a->owner, $this->b)->assertSessionHas('error');

    $this->cashier->memberships()->where('business_id', $this->b->id)->update(['status' => 'inactive']);
    impersonate($this, $this->cashier, $this->b)->assertSessionHas('error');

    $this->b->update(['status' => Business::STATUS_SUSPENDED]);
    impersonate($this, $this->fixture->b->owner, $this->b)->assertSessionHas('error');

    expect(Impersonation::query()->count())->toBe(0)->and(Auth::id())->toBe($this->operator->id);
});

it('isn\'t reachable by a business owner, and ending it needs an impersonation', function () {
    $this->actingAs($this->fixture->a->owner)->post(route('platform.businesses.impersonate', [$this->b, $this->cashier->uuid]), ['reason' => 'x'])
        ->assertNotFound();
    $this->tenant->forget();
    $this->actingAs($this->fixture->a->owner)->post(route('impersonation.end'))->assertNotFound();

    expect(Impersonation::query()->count())->toBe(0);
});
