<?php

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\CashRegister;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\StockIntake;
use App\Models\User;
use App\Services\AuditErasure;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * Phase 1 of account deletion: only the business owner deletes accounts,
 * everyone else can deactivate themselves, deactivation really locks the
 * person out, and deleting someone erases their personal data while the
 * business keeps its records.
 */
beforeEach(function () {
    $this->seed([PermissionSeeder::class, RoleSeeder::class]);

    // The audit store is a separate connection RefreshDatabase does not migrate
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', ['--database' => 'audit', '--path' => 'database/migrations/audit', '--force' => true]);
    }

    $this->business = Business::factory()->create();
    $this->owner = User::factory()->create(['business_id' => $this->business->id, 'email' => 'owner@example.test']);
    $this->owner->assignRole(Role::ADMIN);
    $this->business->update(['owner_id' => $this->owner->id]);

    $this->shop = Shop::factory()->create(['business_id' => $this->business->id, 'manager_id' => $this->owner->id]);

    $this->coAdmin = User::factory()->create(['business_id' => $this->business->id]);
    $this->coAdmin->assignRole(Role::ADMIN);

    $this->staff = User::factory()->create(['business_id' => $this->business->id, 'name' => 'Jane Cashier', 'email' => 'jane@example.test']);
    $this->staff->assignRole('cashier');
    $this->staff->shops()->attach($this->shop);
});

describe('who may delete an account', function () {
    it('lets the business owner delete a staff member', function () {
        $this->actingAs($this->owner)
            ->delete(route('users.destroy', $this->staff))
            ->assertRedirect(route('users.index'));

        expect(User::find($this->staff->id))->toBeNull();
    });

    it('lets a co-admin deactivate or suspend staff but not delete them', function () {
        $this->actingAs($this->coAdmin);

        $this->delete(route('users.destroy', $this->staff))->assertForbidden();
        $this->deleteJson("/api/users/{$this->staff->uuid}")->assertForbidden();
        $this->post(route('users.suspend', $this->staff))->assertRedirect();

        expect($this->staff->fresh()->status)->toBe(UserStatus::SUSPENDED);
    });

    it('does not let a manager delete staff, even holding users.delete', function () {
        $manager = User::factory()->create(['business_id' => $this->business->id]);
        $manager->assignRole('manager');
        $manager->givePermissionTo(['users.delete', 'users.full-access']);
        $manager->shops()->attach($this->shop);

        $this->actingAs($manager)->delete(route('users.destroy', $this->staff))->assertForbidden();

        expect(User::find($this->staff->id))->not->toBeNull();
    });

    it('shows the delete button to the owner only', function () {
        $deleteForm = 'action="'.route('users.destroy', $this->staff).'"';

        $this->actingAs($this->owner)->get(route('users.index'))->assertSee($deleteForm, false);
        $this->actingAs($this->coAdmin)->get(route('users.index'))->assertDontSee($deleteForm, false);
    });
});

describe('deactivating your own account', function () {
    it('has no self-delete any more', function () {
        $this->actingAs($this->staff)
            ->delete('/profile', ['password' => 'password'])
            ->assertStatus(405);

        expect(User::find($this->staff->id))->not->toBeNull();
    });

    it('offers staff "Deactivate my account" instead of "Delete account"', function () {
        $this->actingAs($this->staff)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Deactivate my account')
            ->assertDontSee('Delete Account');
    });

    it('deactivates staff and signs them out', function () {
        $this->actingAs($this->staff)
            ->post(route('profile.deactivate'), ['password' => 'password'])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        expect($this->staff->fresh()->status)->toBe(UserStatus::INACTIVE);
    });

    it('needs the right password', function () {
        $this->actingAs($this->staff)
            ->from(route('profile.edit'))
            ->post(route('profile.deactivate'), ['password' => 'wrong-password'])
            ->assertSessionHasErrorsIn('userDeactivation', 'password');

        expect($this->staff->fresh()->isActive())->toBeTrue();
    });

    it('does not let the owner or a super-admin deactivate themselves', function (string $who) {
        $user = $who === 'owner' ? $this->owner : tap(User::factory()->create(['business_id' => null]))->assignRole(Role::SUPER_ADMIN);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertDontSee('Deactivate my account');

        $this->actingAs($user)
            ->post(route('profile.deactivate'), ['password' => 'password'])
            ->assertSessionHasErrorsIn('userDeactivation', 'password');

        expect($user->fresh()->isActive())->toBeTrue();
    })->with(['owner', 'super-admin']);
});

describe('a deactivated account is locked out', function () {
    it('cannot sign in on the web', function () {
        $this->staff->update(['status' => UserStatus::INACTIVE]);

        $this->post('/login', ['email' => 'jane@example.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    });

    it('is signed out of a session it already had', function () {
        $this->actingAs($this->staff);
        $this->staff->update(['status' => UserStatus::SUSPENDED]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('loses its app tokens the moment it is suspended', function () {
        $this->staff->createToken('till');

        $this->staff->update(['status' => UserStatus::SUSPENDED]);

        expect($this->staff->tokens()->count())->toBe(0);
    });

    it('is refused by the API with a code', function () {
        $this->staff->forceFill(['status' => UserStatus::INACTIVE])->saveQuietly();
        Sanctum::actingAs($this->staff);

        $this->getJson('/api/products')
            ->assertForbidden()
            ->assertJson(['success' => false, 'code' => 'account_inactive']);
    });
});

describe('deleting a staff member', function () {
    it('keeps the business records they touched', function () {
        $register = CashRegister::factory()->create(['shop_id' => $this->shop->id, 'user_id' => $this->staff->id]);
        $intake = StockIntake::factory()->create(['shop_id' => $this->shop->id, 'received_by' => $this->staff->id]);
        $order = PurchaseOrder::factory()->create(['shop_id' => $this->shop->id, 'created_by' => $this->staff->id]);
        $sale = Sale::factory()->create(['shop_id' => $this->shop->id, 'created_by' => $this->staff->id]);

        $this->actingAs($this->owner)
            ->delete(route('users.destroy', $this->staff))
            ->assertRedirect(route('users.index'));

        expect($register->fresh())->not->toBeNull()
            ->and($register->fresh()->user_id)->toBeNull()
            ->and($intake->fresh()->received_by)->toBeNull()
            ->and($order->fresh()->created_by)->toBeNull()
            ->and($sale->fresh()->created_by)->toBeNull();

        $this->get(route('cash-registers.show', $register))->assertOk()->assertSee('Deleted user');
    });

    it('erases their personal data', function () {
        Storage::fake('public');
        config(['session.driver' => 'database']); // as in production; tests default to array
        $photo = UploadedFile::fake()->image('jane.jpg')->store('profile-photos', 'public');
        $this->staff->forceFill(['profile_photo' => $photo])->saveQuietly();
        $this->staff->createToken('till');
        DB::table('sessions')->insert(['id' => 'jane-session', 'user_id' => $this->staff->id, 'payload' => '', 'last_activity' => time()]);

        // Jane acts, and is herself audited
        $this->actingAs($this->staff);
        $this->staff->update(['phone' => '+254700111222']);
        $this->actingAs($this->owner)->delete(route('users.destroy', $this->staff));

        Storage::disk('public')->assertMissing($photo);
        expect(DB::table('personal_access_tokens')->where('tokenable_id', $this->staff->id)->exists())->toBeFalse()
            ->and(DB::table('sessions')->where('user_id', $this->staff->id)->exists())->toBeFalse();

        $rows = AuditLog::query()->where('user_id', $this->staff->id)->get();
        expect($rows)->not->toBeEmpty();
        foreach ($rows as $row) {
            expect($row->user_name)->toBe(AuditErasure::placeholder($this->staff->id))
                ->and($row->user_email)->toBeNull()
                ->and($row->ip_address)->toBeNull();
        }

        $snapshots = AuditLog::query()->where('auditable_type', User::class)->where('auditable_id', $this->staff->id)->get();
        expect($snapshots)->not->toBeEmpty();
        foreach ($snapshots as $row) {
            $text = json_encode([$row->old_values, $row->new_values]);
            expect($text)->not->toContain('Jane Cashier')
                ->not->toContain('jane@example.test')
                ->not->toContain('+254700111222');
        }
    });
});
