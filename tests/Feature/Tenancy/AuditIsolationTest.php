<?php

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\TwoBusinesses;

/**
 * Audit rows carry their business, and every read of the trail stays within
 * the current business (stage 1c). The trail lives on its own connection,
 * where no model scope reaches.
 */
beforeEach(function () {
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', ['--database' => 'audit', '--path' => 'database/migrations/audit', '--force' => true]);
    }

    $this->fixture = TwoBusinesses::create();
    app(TenantContext::class)->forget();
});

/**
 * The newest audit row for a record, read past the business filter.
 */
function auditRowFor(Product $product): object
{
    return DB::connection('audit')->table('audit_logs')
        ->where('auditable_type', Product::class)->where('auditable_id', $product->id)
        ->orderByDesc('id')->first();
}

function signInAuditor(User $user): void
{
    test()->actingAs($user);
    app(TenantContext::class)->forget();
}

it('writes each audit row with its business', function () {
    expect((int) auditRowFor($this->fixture->a->product)->business_id)->toBe($this->fixture->a->business->id)
        ->and((int) auditRowFor($this->fixture->b->product)->business_id)->toBe($this->fixture->b->business->id);
});

it('lists, shows and exports only the current business\'s audit rows', function () {
    $bRow = auditRowFor($this->fixture->b->product);
    signInAuditor($this->fixture->a->owner);

    $listed = $this->get(route('audit-logs.index'))->assertOk()->viewData('logs');
    expect(collect($listed->items())->pluck('business_id')->unique()->values()->all())->toBe([$this->fixture->a->business->id]);

    // Another business's row is not found, even for an owner (who passes every policy).
    $this->get(route('audit-logs.show', $bRow->uuid))->assertNotFound();
    $this->get(route('audit-logs.show', auditRowFor($this->fixture->a->product)->uuid))->assertOk();

    $csv = $this->post(route('audit-logs.export'))->assertOk()->streamedContent();
    expect($csv)->toContain('Alpha Product')->not->toContain('Bravo Product');
});

it('shows no audit rows when no business is set', function () {
    app(TenantContext::class)->forget();
    config(['tenancy.strict' => false]);

    expect(app(AuditService::class)->paginate([], $this->fixture->a->owner)->total())->toBe(0);
});

it('backfills the business of older rows from their shop or record, dry run first', function () {
    $audit = DB::connection('audit')->table('audit_logs');
    $row = fn (array $values): int => $audit->insertGetId([
        'uuid' => (string) Str::uuid(), 'auditable_id' => 1, 'event' => 'updated', 'status' => 'success', 'created_at' => now(),
        ...$values,
    ]);

    $byShop = $row(['auditable_type' => 'App\Models\Sale', 'shop_id' => $this->fixture->b->shops[1]->id]);
    $byRecord = $row(['auditable_type' => Product::class, 'auditable_id' => $this->fixture->a->product->id]);
    // A user belongs to no one business, and this database holds several.
    $unresolved = $row(['auditable_type' => User::class, 'auditable_id' => $this->fixture->both->id]);

    $this->artisan('audit:backfill-business')->assertSuccessful();
    expect(DB::connection('audit')->table('audit_logs')->whereIn('id', [$byShop, $byRecord])->whereNotNull('business_id')->count())->toBe(0);

    $this->artisan('audit:backfill-business', ['--apply' => true])->assertSuccessful();
    $business = fn (int $id) => DB::connection('audit')->table('audit_logs')->where('id', $id)->value('business_id');

    expect((int) $business($byShop))->toBe($this->fixture->b->business->id)
        ->and((int) $business($byRecord))->toBe($this->fixture->a->business->id)
        ->and($business($unresolved))->toBeNull()
        ->and(AuditLog::query()->whereKey($byShop)->exists())->toBeTrue();
});
