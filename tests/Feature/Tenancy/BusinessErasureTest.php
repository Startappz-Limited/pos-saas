<?php

use App\Actions\Business\PurgeBusiness;
use App\Enums\StockMovementType;
use App\Models\Business;
use App\Models\BusinessInvite;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Stock\StockLedger;
use App\Support\Tenancy\BusinessData;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Canaries;
use Tests\Support\TwoBusinesses;

/**
 * Closing a business leaves none of its rows in the main or audit database,
 * and none of its files (stage 3 acceptance criterion); the other business
 * keeps all of its own. Checked by searching every table for business A's
 * names, markers and record uuids, independently of how the erasure finds
 * its rows.
 */
beforeEach(function () {
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', ['--database' => 'audit', '--path' => 'database/migrations/audit', '--force' => true]);
    }
    Storage::fake(TenantFiles::PRIVATE_DISK);
    Storage::fake(TenantFiles::PUBLIC_DISK);

    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->markers = [
        'a' => [...Canaries::plant($this->fixture->a, 'Alpha'), $this->fixture->a->business->uuid, 'Business A'],
        'b' => [...Canaries::plant($this->fixture->b, 'Bravo'), $this->fixture->b->business->uuid, 'Business B'],
    ];

    foreach (['a', 'b'] as $side) {
        $f = $this->fixture->{$side};
        app(TenantContext::class)->run($f->business, function () use ($f): void {
            $variation = $f->product->defaultVariation;
            app(StockLedger::class)->receive($f->shops[0]->id, $variation, 10, 50.0, StockMovementType::INTAKE);
            SaleItem::factory()->create(['sale_id' => $f->sale->id, 'product_id' => $f->product->id, 'shop_id' => $f->shops[1]->id]);
            $account = CreditAccount::factory()->create(['customer_id' => $f->customer->id, 'shop_id' => $f->shops[0]->id]);
            CreditTransaction::factory()->create(['credit_account_id' => $account->id, 'customer_id' => $f->customer->id, 'shop_id' => $f->shops[0]->id]);
            BusinessInvite::factory()->create();
            $f->owner->createToken('till')->accessToken->forceFill(['business_id' => $f->business->id])->save();
            app(TenantFiles::class)->putIfRoom('whatsapp/media.jpg', 'private bytes');
            Storage::disk(TenantFiles::PUBLIC_DISK)->put(app(TenantFiles::class)->path('products/photo.jpg'), 'public bytes');
        });
    }
    app(TenantContext::class)->forget();
});

/**
 * Every row of every table in a connection, as JSON text.
 *
 * @return array<string, string>
 */
function everyTable(?string $connection = null): array
{
    $db = DB::connection($connection);
    $schema = $db->getDriverName() === 'sqlite' ? 'main' : $db->getDatabaseName();
    $dump = [];
    foreach (Schema::connection($connection)->getTableListing($schema, false) as $table) {
        if (! str_starts_with($table, 'sqlite_')) {
            $dump[$table] = json_encode($db->table($table)->get(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    return $dump;
}

/**
 * @param  array<string, string>  $dump
 * @param  list<string>  $markers
 * @return list<string> "table: marker" for every marker found
 */
function markersIn(array $dump, array $markers): array
{
    $found = [];
    foreach ($dump as $table => $rows) {
        foreach ($markers as $marker) {
            if (str_contains($rows, $marker)) {
                $found[] = "{$table}: {$marker}";
            }
        }
    }

    return $found;
}

it('leaves none of a business\'s rows, audit entries or files, and all of the other business\'s', function () {
    $a = $this->fixture->a;
    $b = $this->fixture->b;
    $mainBefore = everyTable();
    $auditBefore = everyTable('audit');
    $bMarkersBefore = markersIn($mainBefore, $this->markers['b']);
    $bRowsBefore = collect((new BusinessData($b->business->id))->ownedIds())->map(fn (array $ids): int => count($ids))->all();

    expect(markersIn($mainBefore, $this->markers['a']))->not->toBeEmpty()
        ->and(DB::connection('audit')->table('audit_logs')->where('business_id', $a->business->id)->exists())->toBeTrue()
        ->and(Storage::disk(TenantFiles::PRIVATE_DISK)->allFiles($a->business->uuid))->not->toBeEmpty();

    $deleted = app(PurgeBusiness::class)->execute($a->business);

    $mainAfter = everyTable();
    expect(markersIn($mainAfter, $this->markers['a']))->toBe([])
        ->and(markersIn(everyTable('audit'), $this->markers['a']))->toBe([])
        ->and(DB::connection('audit')->table('audit_logs')->where('business_id', $a->business->id)->exists())->toBeFalse()
        ->and(Storage::disk(TenantFiles::PRIVATE_DISK)->allFiles($a->business->uuid))->toBe([])
        ->and(Storage::disk(TenantFiles::PUBLIC_DISK)->allFiles($a->business->uuid))->toBe([])
        ->and(Business::find($a->business->id))->toBeNull()
        // Nothing points at A any more, in any table with a business or a shop.
        ->and(collect($mainAfter)->keys()->filter(fn (string $table): bool => Schema::hasColumn($table, 'business_id')
            && DB::table($table)->where('business_id', $a->business->id)->exists())->values()->all())->toBe([])
        ->and(collect($mainAfter)->keys()->filter(fn (string $table): bool => Schema::hasColumn($table, 'shop_id')
            && DB::table($table)->whereIn('shop_id', collect($a->shops)->pluck('id'))->exists())->values()->all())->toBe([]);

    // B is whole: the same rows, markers, audit entries and files.
    expect(markersIn($mainAfter, $this->markers['b']))->toBe($bMarkersBefore)
        ->and(collect((new BusinessData($b->business->id))->ownedIds())->map(fn (array $ids): int => count($ids))->all())->toBe($bRowsBefore)
        ->and(DB::connection('audit')->table('audit_logs')->where('business_id', $b->business->id)->count())
        ->toBe(collect(json_decode($auditBefore['audit_logs'], true))->where('business_id', $b->business->id)->count())
        ->and(Storage::disk(TenantFiles::PRIVATE_DISK)->allFiles($b->business->uuid))->not->toBeEmpty()
        ->and($deleted['sales'] ?? 0)->toBeGreaterThan(0);
});

it('deletes the logins that belong to the business alone, and keeps a login that belongs elsewhere too', function () {
    $a = $this->fixture->a;
    $both = $this->fixture->both;

    app(PurgeBusiness::class)->execute($a->business);

    expect(User::find($a->owner->id))->toBeNull()
        ->and(User::find($a->cashier->id))->toBeNull()
        ->and(User::find($both->id))->not->toBeNull()
        ->and($both->fresh()->default_business_id)->toBeNull()
        ->and($both->memberships()->pluck('business_id')->all())->toBe([$this->fixture->b->business->id])
        ->and(app(TenantContext::class)->run($this->fixture->b->business, fn () => $both->fresh()->hasRole('cashier')))->toBeTrue()
        ->and(DB::table('shop_user')->where('user_id', $both->id)->pluck('shop_id')->all())->toBe([$this->fixture->b->shops[0]->id]);
});
