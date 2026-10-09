<?php

use App\Enums\AuditEvent;
use App\Enums\AuditStatus;
use App\Events\AuditableEvent;
use App\Listeners\AuditLogger;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/**
 * The audit subsystem: capture via the Auditable trait, persistence on the
 * isolated `audit` connection, immutability, secret masking, and the read UI whose
 * routes previously pointed at an empty controller.
 */
beforeEach(function () {
    // RefreshDatabase only migrates the default connection, and the audit
    // connection is a separate in-memory SQLite database during tests — so give it
    // its schema explicitly.
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', [
            '--database' => 'audit',
            '--path' => 'database/migrations/audit',
            '--force' => true,
        ]);
    }

    foreach (['audit.view', 'audit.export', 'products.view', 'products.create'] as $name) {
        Permission::findOrCreate($name);
    }

    $this->shop = Shop::factory()->create();
    $this->otherShop = Shop::factory()->create();

    $this->user = User::factory()->create(['name' => 'Auditor Ann']);
    $this->user->shops()->sync([$this->shop->id]);
    $this->user->givePermissionTo(['audit.view', 'audit.export']);

    // Audit rows are written outside the test transaction (separate connection),
    // so clear the trail between tests to keep assertions deterministic.
    AuditLog::query()->getConnection()->table('audit_logs')->delete();

    $this->actingAs($this->user);
});

// --- storage & isolation ---

it('writes audit rows to the audit connection, not the default one', function () {
    AuditableEvent::dispatch('App\Models\Sale', 1, (string) Str::uuid(), AuditEvent::CREATED);

    expect(AuditLog::count())->toBe(1)
        ->and(AuditLog::first()->getConnectionName())->toBe('audit');
});

it('stamps actor, ip and timestamp automatically', function () {
    AuditableEvent::dispatch(
        auditableType: 'App\Models\Sale',
        auditableId: 1,
        auditableUuid: (string) Str::uuid(),
        event: AuditEvent::CREATED,
        userId: $this->user->id,
        userName: $this->user->name,

    );

    $log = AuditLog::firstOrFail();

    expect($log->user_id)->toBe($this->user->id)
        ->and($log->user_name)->toBe('Auditor Ann')
        ->and($log->ip_address)->not->toBeNull()
        ->and($log->created_at)->not->toBeNull()
        ->and($log->status)->toBe(AuditStatus::SUCCESS);
});

// --- immutability ---

it('refuses to update an existing audit row', function () {
    AuditableEvent::dispatch('App\Models\Sale', 1, (string) Str::uuid(), AuditEvent::CREATED);

    $log = AuditLog::firstOrFail();
    $log->event = AuditEvent::DELETED->value;

    expect(fn () => $log->save())->toThrow(RuntimeException::class, 'immutable');
});

it('refuses to delete an audit row', function () {
    AuditableEvent::dispatch('App\Models\Sale', 1, (string) Str::uuid(), AuditEvent::CREATED);

    expect(fn () => AuditLog::firstOrFail()->delete())->toThrow(RuntimeException::class, 'immutable');
});

// --- failure isolation ---

it('never lets an audit failure break the business flow', function () {
    Log::spy();

    // Point the audit connection at an unusable path so the insert fails. Done via
    // config rather than dropping the table, so the next test gets a clean database
    // (dropping would leave the migration recorded and never recreated).
    config(['database.connections.audit.database' => '/nonexistent-dir/audit.sqlite']);
    DB::purge('audit');

    (new AuditLogger)->handle(
        new AuditableEvent('App\Models\Sale', 1, (string) Str::uuid(), AuditEvent::CREATED)
    );

    // No exception escaped, and the failure was recorded for operators.
    Log::shouldHaveReceived('critical')->once();

    config(['database.connections.audit.database' => ':memory:']);
    DB::purge('audit');
});

// --- sanitisation ---

it('strips secrets and masks pii before storing', function () {
    AuditableEvent::dispatch(
        auditableType: 'App\Models\User',
        auditableId: 1,
        auditableUuid: (string) Str::uuid(),
        event: AuditEvent::UPDATED,
        newValues: [
            'name' => 'Jane',
            'password' => 'super-secret',
            'remember_token' => 'abc123',
            'api_token' => 'tok_live_123',
            'mobile_number' => '254712345678',
            'nested' => ['consumer_secret' => 'cs_123', 'keep' => 'yes'],
        ],
    );

    $values = AuditLog::firstOrFail()->new_values;

    expect($values)->toHaveKey('name')
        ->and($values)->not->toHaveKey('password')
        ->and($values)->not->toHaveKey('remember_token')
        ->and($values)->not->toHaveKey('api_token')
        ->and($values['mobile_number'])->toBe('****5678')
        ->and($values['nested'])->not->toHaveKey('consumer_secret')
        ->and($values['nested']['keep'])->toBe('yes');
});

// --- capture via the trait ---

it('captures a create through the Auditable trait', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Audited Widget']);

    $log = AuditLog::where('auditable_type', Product::class)->firstOrFail();

    expect($log->event)->toBe(AuditEvent::CREATED)
        ->and($log->auditable_uuid)->toBe((string) $product->uuid)
        ->and($log->new_values['name'])->toBe('Audited Widget')
        ->and($log->user_id)->toBe($this->user->id);
});

it('captures an update with before and after values', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Before']);
    AuditLog::query()->getConnection()->table('audit_logs')->delete();

    $product->update(['name' => 'After']);

    $log = AuditLog::where('event', AuditEvent::UPDATED->value)->firstOrFail();

    expect($log->old_values['name'])->toBe('Before')
        ->and($log->new_values['name'])->toBe('After');
});

it('does not write an audit row when nothing meaningful changed', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    AuditLog::query()->getConnection()->table('audit_logs')->delete();

    $product->touch();

    expect(AuditLog::where('event', AuditEvent::UPDATED->value)->count())->toBe(0);
});

it('records the shop context so audit can be shop-filtered', function () {
    Product::factory()->create(['shop_id' => $this->shop->id]);

    expect(AuditLog::where('auditable_type', Product::class)->firstOrFail()->shop_id)
        ->toBe($this->shop->id);
});

// --- read UI ---

it('lists the audit trail', function () {
    Product::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Listed Widget']);

    $logs = $this->get(route('audit-logs.index'))->assertOk()->viewData('logs');

    expect($logs->total())->toBeGreaterThan(0);
});

it('hides another shop\'s audit rows from a restricted user', function () {
    AuditableEvent::dispatch(
        auditableType: 'App\Models\Sale',
        auditableId: 1,
        auditableUuid: (string) Str::uuid(),
        event: AuditEvent::CREATED,
        shopId: $this->otherShop->id,
    );

    $logs = $this->get(route('audit-logs.index'))->assertOk()->viewData('logs');

    expect($logs->total())->toBe(0);
});

it('filters the trail by action', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    $product->update(['name' => 'Changed']);

    $logs = $this->get(route('audit-logs.index', ['event' => AuditEvent::UPDATED->value]))
        ->assertOk()->viewData('logs');

    expect($logs->pluck('event')->unique()->all())->toBe([AuditEvent::UPDATED]);
});

it('rejects an invalid action filter', function () {
    $this->get(route('audit-logs.index', ['event' => 'not-an-action']))
        ->assertSessionHasErrors('event');
});

it('shows a single audit entry', function () {
    Product::factory()->create(['shop_id' => $this->shop->id]);
    $log = AuditLog::firstOrFail();

    $this->get(route('audit-logs.show', $log))->assertOk();
});

it('shows the full history for one record', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    $product->update(['name' => 'Second']);

    $logs = $this->get(route('audit-logs.forModel', ['type' => 'Product', 'id' => $product->uuid]))
        ->assertOk()->viewData('logs');

    expect($logs)->toHaveCount(2);
});

it('404s for an unknown entity type', function () {
    $this->get(route('audit-logs.forModel', ['type' => 'NotAModel', 'id' => (string) Str::uuid()]))
        ->assertNotFound();
});

it('shows the trail for one actor', function () {
    Product::factory()->create(['shop_id' => $this->shop->id]);

    $logs = $this->get(route('audit-logs.forUser', $this->user))->assertOk()->viewData('logs');

    expect($logs->total())->toBeGreaterThan(0);
});

it('exports the trail as csv', function () {
    Product::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Exported Widget']);

    $response = $this->post(route('audit-logs.export'))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())->toContain('Logged At')->toContain('Product');
});

// --- authorization ---

it('denies the trail without the audit.view permission', function () {
    $outsider = User::factory()->create();
    $outsider->shops()->sync([$this->shop->id]);

    $this->actingAs($outsider)->get(route('audit-logs.index'))->assertForbidden();
});

it('denies export without the audit.export permission', function () {
    $viewer = User::factory()->create();
    $viewer->shops()->sync([$this->shop->id]);
    $viewer->givePermissionTo('audit.view');

    $this->actingAs($viewer)->post(route('audit-logs.export'))->assertForbidden();
});
