<?php

use App\Http\Middleware\ResolveTenant;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/**
 * Being owner of one business gives nothing in another (stage 10a, gap 2,
 * decided 28 October 2026). Roles are per business (Spatie teams), but a
 * `User` instance that already loaded its `roles`/`permissions` relations
 * must not keep answering for the business it was loaded under once asked
 * about a different one — the risk `User::refreshRolesForCurrentBusiness()`
 * closes.
 */
beforeEach(function () {
    ownerRole();
    sharedRole('cashier');

    $this->a = Business::factory()->create(['name' => 'Business A']);
    $this->b = Business::factory()->create(['name' => 'Business B']);

    $this->shopA = app(TenantContext::class)->run($this->a, fn () => Shop::factory()->create(['name' => 'A Main']));
    $this->shopB = app(TenantContext::class)->run($this->b, fn () => Shop::factory()->create(['name' => 'B Main']));

    // Alice: owner of A, cashier (no extra permissions) in B.
    $this->alice = User::factory()->memberOf($this->a)->create(['name' => 'Alice']);
    app(TenantContext::class)->run($this->a, fn () => $this->alice->assignRole('admin'));
    $this->alice->joinBusiness($this->b);
    app(TenantContext::class)->run($this->b, fn () => $this->alice->assignRole('cashier'));
    $this->alice->shops()->attach($this->shopB->id);

    // Oscar: owner of A only.
    $this->oscar = User::factory()->memberOf($this->a)->create(['name' => 'Oscar']);
    app(TenantContext::class)->run($this->a, fn () => $this->oscar->assignRole('admin'));

    app(TenantContext::class)->forget();
});

it('reloads a User\'s roles when asked about a different business than the one they were loaded under', function () {
    // Loaded, and its roles relation populated, while A is set.
    $alice = app(TenantContext::class)->run($this->a, function () {
        $alice = User::find($this->alice->id);
        expect($alice->isBusinessOwner())->toBeTrue();

        return $alice;
    });

    // The same instance, asked again after the business changed under it
    // (as a job looping with eachBusiness() does): answers for B now.
    app(TenantContext::class)->run($this->b, function () use ($alice) {
        expect($alice->isBusinessOwner())->toBeFalse()
            ->and($alice->hasRole('cashier'))->toBeTrue()
            ->and($alice->can('users.update'))->toBeFalse();
    });

    // And back to A once more.
    app(TenantContext::class)->run($this->a, function () use ($alice) {
        expect($alice->isBusinessOwner())->toBeTrue();
    });
});

it('gives an owner of A, cashier in B, only a cashier\'s rights in B', function () {
    app(TenantContext::class)->run($this->b, function () {
        $alice = $this->alice->fresh();

        expect($alice->isBusinessOwner())->toBeFalse()
            ->and($alice->can('users.update'))->toBeFalse()
            ->and($alice->can('users.delete'))->toBeFalse()
            ->and($alice->can('settings.update'))->toBeFalse()
            ->and($alice->can('products.view-cost'))->toBeFalse()
            ->and($alice->can('sales.void'))->toBeFalse();
    });

    // Their own user page in B offers no Edit for others.
    $this->actingAs($this->alice)->withSession([ResolveTenant::SESSION_KEY => $this->b->id]);
    app(TenantContext::class)->forget();
    $otherMemberOfB = app(TenantContext::class)->run($this->b, fn () => User::factory()->memberOf($this->b)->create());

    $this->get(route('users.edit', $otherMemberOfB))->assertForbidden();
});

it('refuses an owner of A every B resource, as a 404', function () {
    $this->actingAs($this->oscar)->withSession([ResolveTenant::SESSION_KEY => $this->b->id]);
    app(TenantContext::class)->forget();

    // Oscar isn't a member of B at all: the session choice is ignored
    // (ResolveTenant), so these resolve against A, where Oscar is owner —
    // genuinely reaching B is impossible through any surface.
    $bCustomer = app(TenantContext::class)->run($this->b, fn () => Customer::factory()->forShop($this->shopB)->create(['name' => 'B Customer']));
    app(TenantContext::class)->forget();

    $this->actingAs($this->oscar)->withSession([ResolveTenant::SESSION_KEY => $this->b->id]);
    app(TenantContext::class)->forget();
    $this->get('/dashboard')->assertOk();
    $this->get(route('customers.show', $bCustomer))->assertNotFound();
});

it('runs a business command as the system, with no person\'s rights', function () {
    Auth::forgetGuards();

    $seenUser = 'not run';
    app(TenantContext::class)->run($this->a, function () use (&$seenUser) {
        $seenUser = Auth::user();
    });

    expect($seenUser)->toBeNull();
});

it('restores the business a job was dispatched in, even once another is the worker\'s last business', function () {
    RecordsIsBusinessOwnerForTest::$result = null;
    config(['queue.default' => 'database']);

    app(TenantContext::class)->run($this->a, function () {
        RecordsIsBusinessOwnerForTest::dispatch($this->alice->id);
    });

    // The worker's last business, before it picks up Alice's job.
    app(TenantContext::class)->set($this->b);

    Artisan::call('queue:work', ['--once' => true, '--queue' => 'default']);

    expect(RecordsIsBusinessOwnerForTest::$result)->not->toBeNull()
        ->and(RecordsIsBusinessOwnerForTest::$result['business_id'])->toBe($this->a->id)
        ->and(RecordsIsBusinessOwnerForTest::$result['is_owner'])->toBeTrue();
});

it('audits a switch of the default business, and acts as owner or cashier depending on it', function () {
    migrateAuditDatabase();
    $this->alice->forceFill(['default_business_id' => $this->a->id])->save();

    $this->actingAs($this->alice);
    app(TenantContext::class)->forget();
    $this->post(route('businesses.default', $this->b))->assertRedirect();

    expect($this->alice->fresh()->default_business_id)->toBe($this->b->id);

    $row = AuditLog::query()->where('auditable_type', User::class)->where('auditable_id', $this->alice->id)
        ->whereJsonContains('tags', 'default_business_changed')->latest('id')->first();

    expect($row)->not->toBeNull()
        ->and($row->new_values['default_business_id'])->toBe($this->b->id)
        ->and($row->old_values['default_business_id'])->toBe($this->a->id)
        ->and($row->new_values['reason'])->toBe('switcher')
        ->and($row->user_id)->toBe($this->alice->id);

    // Acting with their new default: a cashier's rights, not an owner's.
    app(TenantContext::class)->run($this->b, fn () => expect($this->alice->fresh()->isBusinessOwner())->toBeFalse());
    app(TenantContext::class)->run($this->a, fn () => expect($this->alice->fresh()->isBusinessOwner())->toBeTrue());
});

/**
 * A minimal queued job for the Phase 1c restore test above: records the
 * business TenantContext resolves to, and whether the given user is that
 * business's owner, entirely from what the job itself sees on the worker.
 */
class RecordsIsBusinessOwnerForTest implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue, Queueable, SerializesModels;

    public static ?array $result = null;

    public function __construct(public int $userId) {}

    public function handle(TenantContext $tenant): void
    {
        $user = User::find($this->userId);

        self::$result = [
            'business_id' => $tenant->id(),
            'is_owner' => $user?->isBusinessOwner(),
        ];
    }
}
