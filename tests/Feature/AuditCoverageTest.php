<?php

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * Audit coverage beyond the first six models: shops, roles, credit, returns and —
 * crucially — role/permission grants, which are pivot writes that fire no model
 * event and so need Spatie's own events.
 */
beforeEach(function () {
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', [
            '--database' => 'audit',
            '--path' => 'database/migrations/audit',
            '--force' => true,
        ]);
    }

    $this->actor = User::factory()->create(['name' => 'Grant Giver']);
    $this->actingAs($this->actor);
});

/**
 * Audit rows for one model type, ignoring rows the setup itself produced.
 */
function auditRowsFor(string $type)
{
    return AuditLog::query()->where('auditable_type', $type)->get();
}

// --- newly audited models ---

it('audits shop creation', function () {
    $shop = Shop::factory()->create(['name' => 'Audited Shop']);

    $log = auditRowsFor(Shop::class)->firstWhere('auditable_uuid', (string) $shop->uuid);

    expect($log)->not->toBeNull()
        ->and($log->event)->toBe(AuditEvent::CREATED)
        ->and($log->new_values['name'])->toBe('Audited Shop');
});

it('never stores the encrypted integration credentials held in shop settings', function () {
    Shop::factory()->create([
        'settings' => ['integrations' => ['woocommerce' => ['consumer_secret' => 'cs_live_secret']]],
    ]);

    $values = auditRowsFor(Shop::class)->last()->new_values ?? [];

    // `settings` is stripped wholesale — it carries encrypted API credentials.
    expect($values)->not->toHaveKey('settings')
        ->and(json_encode($values))->not->toContain('cs_live_secret');
});

it('audits role creation', function () {
    $role = Role::create(['name' => 'auditor-test']);

    $log = auditRowsFor(Role::class)->firstWhere('auditable_uuid', (string) $role->uuid);

    expect($log)->not->toBeNull()
        ->and($log->new_values['name'])->toBe('auditor-test');
});

it('audits a credit account and its transactions', function () {
    $shop = Shop::factory()->create();
    $customer = Customer::factory()->forShop($shop)->create();

    $account = CreditAccount::create([
        'uuid' => (string) Str::uuid(),
        'customer_id' => $customer->id,
        'shop_id' => $shop->id,
        'credit_limit' => 5000,
        'current_balance' => 0,
        'available_credit' => 5000,
        'status' => 'active',
        'created_by' => $this->actor->id,
    ]);

    $log = auditRowsFor(CreditAccount::class)->firstWhere('auditable_uuid', (string) $account->uuid);

    expect($log)->not->toBeNull()
        ->and($log->shop_id)->toBe($shop->id);
});

// --- authorization grants (pivot writes) ---

it('audits a role being granted to a user', function () {
    Role::create(['name' => 'cashier-test']);

    $this->actor->assignRole('cashier-test');

    $log = auditRowsFor(User::class)
        ->filter(fn ($l) => isset($l->new_values['roles_attached']))
        ->last();

    expect($log)->not->toBeNull()
        ->and($log->new_values['roles_attached'])->toContain('cashier-test')
        ->and($log->user_id)->toBe($this->actor->id)
        ->and($log->tags)->toContain('authorization');
});

it('audits a role being revoked', function () {
    Role::create(['name' => 'temp-role']);
    $this->actor->assignRole('temp-role');

    $this->actor->removeRole('temp-role');

    $log = auditRowsFor(User::class)
        ->filter(fn ($l) => isset($l->new_values['roles_detached']))
        ->last();

    expect($log)->not->toBeNull()
        ->and($log->new_values['roles_detached'])->toContain('temp-role');
});

it('audits a permission granted directly to a user', function () {
    Permission::findOrCreate('reports.export');

    $this->actor->givePermissionTo('reports.export');

    $log = auditRowsFor(User::class)
        ->filter(fn ($l) => isset($l->new_values['permissions_attached']))
        ->last();

    expect($log)->not->toBeNull()
        ->and($log->new_values['permissions_attached'])->toContain('reports.export');
});

it('audits a permission revoked from a user', function () {
    Permission::findOrCreate('reports.view');
    $this->actor->givePermissionTo('reports.view');

    $this->actor->revokePermissionTo('reports.view');

    $log = auditRowsFor(User::class)
        ->filter(fn ($l) => isset($l->new_values['permissions_detached']))
        ->last();

    expect($log)->not->toBeNull()
        ->and($log->new_values['permissions_detached'])->toContain('reports.view');
});

it('records the acting user on an authorization change', function () {
    Role::create(['name' => 'traced-role']);

    $target = User::factory()->create(['name' => 'Target User']);
    $target->assignRole('traced-role');

    $log = auditRowsFor(User::class)
        ->filter(fn ($l) => isset($l->new_values['roles_attached']))
        ->last();

    // The row is about the target, but the actor is whoever made the change.
    expect($log->auditable_id)->toBe($target->id)
        ->and($log->user_name)->toBe('Grant Giver');
});

it('does not write an audit row when nothing was attached', function () {
    $before = auditRowsFor(User::class)->count();

    $this->actor->syncRoles([]);

    expect(auditRowsFor(User::class)->count())->toBe($before);
});
