<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use App\Support\Sales\VoidWindow;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\TwoBusinesses;

/*
 * Who can void a sale, and for how long (stage 10, step 2). Only sales.void
 * allows it (the owner's by default), web and flat API apply the same policy,
 * and the business's void window holds everyone, the owner included. The
 * server runs on UTC here (as in production; .env sets Nairobi locally), so
 * times are UTC unless named: Nairobi is UTC+3.
 */

beforeEach(function () {
    $this->serverTimezone = date_default_timezone_get();
    config(['app.timezone' => 'UTC']);
    date_default_timezone_set('UTC');
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-11-02 07:00:00')); // Monday 10:00 Nairobi
    $this->shop = Shop::factory()->create(['timezone' => null]);
    $this->owner = User::factory()->create();
    $this->owner->assignRole(ownerRole());
    $this->owner->shops()->attach($this->shop);
    $this->window = app(VoidWindow::class);
    $this->session = fn (): CashRegister => CashRegister::factory()->create(['shop_id' => $this->shop->id, 'user_id' => $this->owner->id]);
    $this->sale = fn (array $attributes = []): Sale => Sale::factory()->create([
        'shop_id' => $this->shop->id, 'register_id' => ($this->session)()->id, 'status' => 'completed', 'completed_at' => now(), ...$attributes,
    ]);
    $this->setWindow = function (string|int $value, ?Business $business = null): void {
        $business ??= testBusiness();
        $business->update(['settings' => array_replace_recursive($business->settings ?? [], ['sales' => ['void_window' => $value]])]);
    };
    $this->member = function (string|array $roleOrPermissions): User {
        $user = User::factory()->create();
        is_string($roleOrPermissions) ? $user->assignRole($roleOrPermissions) : $user->givePermissionTo($roleOrPermissions);
        $user->shops()->attach($this->shop);

        return $user;
    };
    $this->voidWeb = fn (User $user, Sale $sale) => $this->actingAs($user)->post(route('sales.void', $sale), ['void_reason' => 'Keyed in error']);
    $this->voidApi = function (User $user, Sale $sale) {
        Sanctum::actingAs($user, ['*']);

        return $this->postJson("/api/sales/{$sale->getRouteKey()}/void", ['void_reason' => 'Keyed in error']);
    };
});

afterEach(function () {
    date_default_timezone_set($this->serverTimezone);
});

describe('who', function () {
    it('refuses a manager with no grant on the web and the flat API', function () {
        $manager = ($this->member)('manager');
        $sale = ($this->sale)();

        ($this->voidWeb)($manager, $sale)->assertForbidden();
        ($this->voidApi)($manager, $sale)->assertForbidden()->assertJsonStructure(['message']);
        expect($sale->fresh()->status)->toBe('completed');
    });

    it('lets a role the owner gave sales.void void a sale', function () {
        $role = app(TenantContext::class)->run(testBusiness(), fn () => Role::create(['name' => 'shift-lead', 'guard_name' => 'web', 'business_id' => testBusiness()->id]));
        $role->givePermissionTo('sales.void');
        $lead = ($this->member)('shift-lead');

        ($this->voidApi)($lead, ($this->sale)())->assertOk();
        ($this->voidWeb)($lead, ($this->sale)())->assertRedirect()->assertSessionHasNoErrors();
    });

    it('refuses sales.edit-all alone', function () {
        ($this->voidApi)(($this->member)(['sales.view', 'sales.view-all', 'sales.edit-all']), ($this->sale)())->assertForbidden();
    });

    it('lets a pending sale be voided days later, but not by a manager without sales.void', function () {
        $sale = ($this->sale)(['status' => 'pending', 'completed_at' => null, 'paid_amount' => 0]);
        $this->travel(5)->days();

        expect(($this->member)('manager')->can('void', $sale))->toBeFalse()
            ->and($this->window->until($sale))->toBeNull()
            ->and($this->owner->can('void', $sale))->toBeTrue();

        // Once completed, the session window runs from its completion.
        $sale->update(['status' => 'completed', 'completed_at' => now()]);
        expect($this->owner->can('void', $sale->fresh()))->toBeTrue();
        $sale->register->update(['status' => 'closed', 'closed_at' => now()->addMinute()]);
        $this->travel(2)->minutes();
        expect($this->owner->can('void', $sale->fresh()))->toBeFalse();
    });

    it('answers 404 for another business\'s sale', function () {
        $two = TwoBusinesses::create();
        app(TenantContext::class)->forget();

        $this->actingAs($two->a->owner)->post("/sales/{$two->b->sale->uuid}/void", ['void_reason' => 'x'])->assertNotFound();
    });
});

describe('the session window (default)', function () {
    it('allows a void while the session is open and refuses it once the session closes, even the same day', function () {
        $sale = ($this->sale)();
        expect($this->owner->can('void', $sale))->toBeTrue();

        $this->travel(1)->hours();
        $sale->register->update(['status' => 'closed', 'closed_at' => now()]);
        $this->travel(1)->minutes();

        ($this->voidApi)($this->owner, $sale->fresh())->assertUnprocessable()
            ->assertJsonPath('code', 'void_window_closed')
            ->assertJsonPath('message', "This sale's register session has closed, so it can't be voided. Use a return instead.");
    });

    it('stops voids at midnight for a session left open overnight', function () {
        $this->travelTo(Carbon::parse('2026-11-02 17:00:00')); // 20:00 Nairobi
        $sale = ($this->sale)();

        $this->travelTo(Carbon::parse('2026-11-02 20:59:59')); // 23:59:59 Nairobi
        expect($this->owner->can('void', $sale))->toBeTrue();
        $this->travelTo(Carbon::parse('2026-11-02 21:01:00')); // 00:01 Nairobi
        ($this->voidApi)($this->owner, $sale)->assertUnprocessable()
            ->assertJsonPath('message', 'This sale can only be voided on the day it was made. Use a return instead.');
    });

    it('uses the end of the day for a sale with no session, and when the setting is missing', function () {
        expect(VoidWindow::setting(testBusiness()))->toBe(VoidWindow::SESSION);
        $sale = ($this->sale)(['register_id' => null]);

        expect($this->window->until($sale)->toIso8601String())->toBe('2026-11-02T20:59:59+00:00');
    });

    it('allows an offline void recorded before its sale\'s session closed, and refuses one recorded after', function () {
        $sale = ($this->sale)();
        $sale->register->update(['status' => 'closed', 'closed_at' => Carbon::parse('2026-11-02 12:00:00')]);
        $this->travelTo(Carbon::parse('2026-11-03 07:00:00')); // pushed the next day

        expect($this->window->allows($sale->fresh(), Carbon::parse('2026-11-02 11:59:00')))->toBeTrue()
            ->and($this->window->allows($sale->fresh(), Carbon::parse('2026-11-02 12:01:00')))->toBeFalse();
    });
});

describe('the other windows', function () {
    it('with end_of_day, allows a 10:00 sale at 23:59 and refuses it at 00:00 the next day', function () {
        ($this->setWindow)(VoidWindow::END_OF_DAY);
        $sale = ($this->sale)();
        $sale->register->update(['status' => 'closed', 'closed_at' => now()->addHour()]);

        expect($this->window->allows($sale->fresh(), Carbon::parse('2026-11-02 20:59:00')))->toBeTrue()
            ->and($this->window->allows($sale->fresh(), Carbon::parse('2026-11-02 21:00:00')))->toBeFalse();
    });

    it('refuses a sale made at 23:50 at 00:01', function () {
        ($this->setWindow)(VoidWindow::END_OF_DAY);
        $this->travelTo(Carbon::parse('2026-11-02 20:50:00'));
        $sale = ($this->sale)();
        $this->travelTo(Carbon::parse('2026-11-02 21:01:00'));

        expect($this->owner->can('void', $sale))->toBeFalse();
    });

    it('reads the day in the shop\'s timezone, not UTC', function () {
        ($this->setWindow)(VoidWindow::END_OF_DAY);
        $this->travelTo(Carbon::parse('2026-11-02 19:00:00')); // 22:00 Nairobi
        $sale = ($this->sale)();
        $this->travelTo(Carbon::parse('2026-11-02 21:30:00')); // 00:30 Nairobi, still the 2nd in UTC

        expect($this->owner->can('void', $sale))->toBeFalse();
    });

    it('uses a shop\'s own timezone', function () {
        ($this->setWindow)(VoidWindow::END_OF_DAY);
        $this->shop->update(['timezone' => 'Europe/London']);
        $this->travelTo(Carbon::parse('2026-11-02 20:00:00')); // 23:00 Nairobi, 20:00 London
        $sale = ($this->sale)();
        $this->travelTo(Carbon::parse('2026-11-02 21:30:00')); // 00:30 Nairobi, 21:30 London

        expect($this->owner->can('void', $sale))->toBeTrue();
    });

    it('with 60 minutes, allows a void at 30 and refuses one at 61', function () {
        ($this->setWindow)(60);
        $sale = ($this->sale)();

        $this->travel(30)->minutes();
        expect($this->owner->can('void', $sale))->toBeTrue();
        $this->travel(31)->minutes();
        ($this->voidApi)($this->owner, $sale)->assertUnprocessable()
            ->assertJsonPath('message', 'This sale can no longer be voided (limit: 60 minutes). Use a return instead.');
    });

    it('with no time limit, allows a week-old sale', function () {
        ($this->setWindow)(VoidWindow::NONE);
        $sale = ($this->sale)();
        $sale->register->update(['status' => 'closed', 'closed_at' => now()]);
        $this->travel(7)->days();

        ($this->voidApi)($this->owner, $sale->fresh())->assertOk();
    });

    it('holds the owner to the limit the next day: 422 with the message, back to the form on the web', function () {
        $sale = ($this->sale)();
        $this->travel(1)->days();

        ($this->voidWeb)($this->owner, $sale)->assertRedirect()->assertSessionHas('error', 'This sale can only be voided on the day it was made. Use a return instead.');
        $this->actingAs($this->owner)->postJson(route('sales.void', $sale), ['void_reason' => 'Keyed in error'])->assertUnprocessable()
            ->assertJsonPath('message', 'This sale can only be voided on the day it was made. Use a return instead.');
        ($this->voidApi)($this->owner, $sale)->assertUnprocessable();
        expect($sale->fresh()->status)->toBe('completed');
    });

    it('checks an offline void at its recorded time: a Monday void of a Monday sale pushed on Tuesday is allowed, a Tuesday one is not', function () {
        ($this->setWindow)(VoidWindow::END_OF_DAY);
        $sale = ($this->sale)(['register_id' => null]);
        $this->travelTo(Carbon::parse('2026-11-03 06:00:00')); // Tuesday

        expect($this->window->allows($sale, Carbon::parse('2026-11-02 15:00:00')))->toBeTrue()
            ->and($this->window->allows($sale, now()))->toBeFalse();
    });

    it('applies only the sale\'s own business\'s setting', function () {
        $other = Business::factory()->create();
        ($this->setWindow)(VoidWindow::NONE, $other);
        $sale = ($this->sale)();
        $this->travel(1)->days();

        expect($this->owner->can('void', $sale))->toBeFalse();
    });
});

describe('the setting', function () {
    it('is changed only by the owner, audited; settings.update alone is refused', function () {
        migrateAuditDatabase();
        $business = testBusiness();
        $form = ['name' => $business->name, 'currency' => $business->currency, 'timezone' => $business->timezone];

        $this->actingAs(($this->member)('cashier'))->put(route('business.settings.update'), [...$form, 'void_window' => 'none'])->assertForbidden();
        $this->actingAs(($this->member)(['settings.update']))->put(route('business.settings.update'), [...$form, 'void_window' => 'none'])->assertForbidden();
        expect(VoidWindow::setting($business->fresh()))->toBe(VoidWindow::SESSION);

        $this->actingAs($this->owner)->put(route('business.settings.update'), [...$form, 'void_window' => 'minutes', 'void_window_minutes' => 1441])
            ->assertSessionHasErrors('void_window_minutes');
        $this->actingAs($this->owner)->put(route('business.settings.update'), [...$form, 'void_window' => 'minutes', 'void_window_minutes' => 90])
            ->assertSessionHasNoErrors();

        expect(VoidWindow::setting($business->fresh()))->toBe(90);
        $audit = AuditLog::query()->where('auditable_type', Business::class)->latest('id')->first();
        expect($audit->old_values)->toBe(['sales.void_window' => 'session'])
            ->and($audit->new_values)->toBe(['sales.void_window' => 90])
            ->and($audit->user_id)->toBe($this->owner->id);
    });
});

describe('the migration', function () {
    it('takes sales.void off the manager template and every copy, leaves custom roles alone, and permissions:sync doesn\'t give it back', function () {
        $business = Business::factory()->create();
        $template = Role::withoutGlobalScopes()->whereNull('business_id')->where('name', 'manager')->firstOrFail();
        $copy = Role::withoutGlobalScopes()->where('business_id', $business->id)->where('name', 'manager')->firstOrFail();
        $custom = app(TenantContext::class)->run($business, fn () => Role::create(['name' => 'shift-lead', 'guard_name' => 'web', 'business_id' => $business->id]));
        // As before this stage: the template and its copy hold it, and an owner gave the custom role it.
        foreach ([$template, $copy, $custom] as $role) {
            $role->givePermissionTo('sales.void');
        }
        DB::table('role_revoked_permissions')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        (require database_path('migrations/2026_11_03_000004_revoke_manager_sales_void.php'))->up();
        $this->artisan('permissions:sync')->assertSuccessful();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $void = Permission::findByName('sales.void');
        expect($template->fresh()->hasPermissionTo($void))->toBeFalse()
            ->and($copy->fresh()->hasPermissionTo($void))->toBeFalse()
            ->and($custom->fresh()->hasPermissionTo($void))->toBeTrue()
            ->and(DB::table('role_revoked_permissions')->where('role_id', $copy->id)->where('permission_id', $void->id)->exists())->toBeTrue();
    });

    it('keeps sales.void on a copy whose owner gave it on purpose, as the audit trail shows', function () {
        migrateAuditDatabase();
        $void = Permission::findByName('sales.void');
        [$fromTemplate, $byOwner, $byOperator] = collect(range(1, 3))->map(function () use ($void) {
            $business = Business::factory()->create();
            $copy = Role::withoutGlobalScopes()->where('business_id', $business->id)->where('name', 'manager')->firstOrFail();
            // From the template: written directly, as copying and the seeder do (no audited attach).
            DB::table('role_has_permissions')->insertOrIgnore(['role_id' => $copy->id, 'permission_id' => $void->id]);

            return [$business, $copy];
        })->all();

        // An owner removed it and gave it back on purpose; an operator's grant doesn't count as the owner's.
        $owner = User::factory()->memberOf($byOwner[0])->create();
        $operator = User::factory()->create(['is_platform_operator' => true]);
        foreach ([[$byOwner, $owner], [$byOperator, $operator]] as [[$business, $copy], $person]) {
            $this->actingAs($person);
            app(TenantContext::class)->run($business, function () use ($copy): void {
                $copy->revokePermissionTo('sales.void');
                $copy->givePermissionTo('sales.void');
            });
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        (require database_path('migrations/2026_11_03_000004_revoke_manager_sales_void.php'))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        expect($fromTemplate[1]->fresh()->hasPermissionTo($void))->toBeFalse()
            ->and($byOwner[1]->fresh()->hasPermissionTo($void))->toBeTrue()
            ->and($byOperator[1]->fresh()->hasPermissionTo($void))->toBeFalse()
            ->and(DB::table('role_revoked_permissions')->where('role_id', $byOwner[1]->id)->exists())->toBeFalse();
    });
});

// TEMPORARY until step 4 refunds payments on a void (decided 3 November 2026): remove with the refusal.
describe('a pending sale with payments (temporary)', function () {
    it('refuses to void a pending sale that has payments, with 422 sale_has_payments', function () {
        $sale = ($this->sale)(['status' => 'pending', 'completed_at' => null, 'paid_amount' => 500]);

        ($this->voidApi)($this->owner, $sale)->assertUnprocessable()
            ->assertJsonPath('code', 'sale_has_payments')
            ->assertJsonPath('message', "This sale has payments. It can't be voided until refunds are supported.");
        ($this->voidWeb)($this->owner, $sale)->assertRedirect()->assertSessionHas('error', "This sale has payments. It can't be voided until refunds are supported.");
        expect($sale->fresh()->status)->toBe('pending');

        // Without payments it can still be voided at any time.
        ($this->voidApi)($this->owner, ($this->sale)(['status' => 'pending', 'completed_at' => null, 'paid_amount' => 0]))->assertOk();
    });
});
