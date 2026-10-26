<?php

use App\Enums\ApiErrorCode;
use App\Models\Customer;
use App\Models\Register;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * Sync pull, Phase 7 (step 5): a paired till gets what changed in its
 * business since its cursor, deletions included, its own shop's stock only,
 * and staff and settings when they differ; never another business's rows.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    [$this->shop1, $this->shop2] = array_values($this->a->shops);
    $this->register = $this->tenant->run($this->a->business, fn () => Register::defaultFor($this->shop1->id));
    $this->tenant->forget();
    $this->token = Till::token($this->a->owner, $this->register);
    // Past the second the fixture was written in, so all of it can be pulled.
    $this->travel(2)->seconds();
});

/**
 * GET /api/v1/sync/pull as the till.
 */
function pullAsTill(string $token, ?string $since = null, ?int $limit = null)
{
    Till::freshRequest();

    return test()->withToken($token)->getJson('/api/v1/sync/pull?'.http_build_query(array_filter(['since' => $since, 'limit' => $limit])));
}

it('sends the whole business on the first pull, its own shop\'s stock only, and nothing of another business', function () {
    $response = pullAsTill($this->token)->assertOk()
        ->assertJsonStructure(['data' => ['categories', 'products', 'variations', 'pricing_rules', 'customers', 'stock', 'deletions', 'staff', 'settings'], 'meta' => ['cursor', 'has_more', 'lease', 'server_time']])
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('data.settings.register.uuid', (string) $this->register->uuid)
        ->assertJsonPath('data.settings.shop.uuid', (string) $this->shop1->uuid);

    $body = json_encode($response->json('data'));
    $productUuids = collect($response->json('data.products'))->pluck('uuid');

    expect($productUuids)->toContain($this->a->product->uuid)
        ->not->toContain($this->fixture->b->product->uuid)
        ->and(collect($response->json('data.customers'))->pluck('uuid'))->toContain($this->a->customer->uuid)
        ->and($body)->not->toContain($this->fixture->b->customer->uuid)
        ->and($body)->not->toContain($this->fixture->b->business->uuid);

    // Stock: the till's shop only, every row of it.
    $shopRows = $this->tenant->run($this->a->business, fn () => DB::table('shop_variation')->where('shop_id', $this->shop1->id)->count());
    expect($response->json('data.stock'))->not->toBeEmpty()->toHaveCount($shopRows);
});

it('sends only what changed since the cursor, and staff and settings only when they differ', function () {
    $cursor = pullAsTill($this->token)->json('meta.cursor');

    $again = pullAsTill($this->token, $cursor)->assertOk();
    expect($again->json('data.products'))->toBe([])
        ->and($again->json('data.customers'))->toBe([])
        ->and($again->json('data.staff'))->toBeNull()
        ->and($again->json('data.settings'))->toBeNull();

    $this->travel(5)->seconds();
    $this->tenant->run($this->a->business, fn () => $this->a->product->forceFill(['name' => 'Renamed'])->save());
    $this->travel(2)->seconds();

    $changed = pullAsTill($this->token, $again->json('meta.cursor'))->assertOk();
    expect(collect($changed->json('data.products'))->pluck('name')->all())->toBe(['Renamed'])
        ->and($changed->json('data.customers'))->toBe([]);
});

it('sends deletions, soft deletes included, and never the deleted record itself', function () {
    $cursor = pullAsTill($this->token)->json('meta.cursor');
    $this->travel(5)->seconds();

    $this->tenant->run($this->a->business, function (): void {
        $this->a->product->delete();
        $this->a->customer->delete();
    });
    $this->travel(2)->seconds();

    $response = pullAsTill($this->token, $cursor)->assertOk();

    expect(collect($response->json('data.deletions'))->map(fn ($d) => $d['entity'].':'.$d['uuid'])->all())
        ->toContain('products:'.$this->a->product->uuid)
        ->toContain('customers:'.$this->a->customer->uuid)
        ->and(collect($response->json('data.products'))->pluck('uuid'))->not->toContain($this->a->product->uuid);
});

it('pages a large pull with the cursor, sending every row exactly once', function () {
    $this->tenant->run($this->a->business, fn () => Customer::factory()->count(5)->create());
    $this->travel(2)->seconds();

    $seen = [];
    $cursor = null;
    $pulls = 0;
    do {
        $response = pullAsTill($this->token, $cursor, 2)->assertOk();
        array_push($seen, ...collect($response->json('data.customers'))->pluck('uuid')->all());
        $cursor = $response->json('meta.cursor');
        $pulls++;
    } while ($response->json('meta.has_more') && $pulls < 20);

    $all = $this->tenant->run($this->a->business, fn () => Customer::query()->pluck('uuid')->all());

    expect($seen)->toHaveCount(count($all))
        ->and(array_unique($seen))->toHaveCount(count($all))
        ->and($pulls)->toBeGreaterThan(1);
});

it('leaves a row written in the current second for the next pull', function () {
    $cursor = pullAsTill($this->token)->json('meta.cursor');
    $this->tenant->run($this->a->business, fn () => $this->a->customer->forceFill(['name' => 'Just now'])->save());

    expect(pullAsTill($this->token, $cursor)->json('data.customers'))->toBe([]);

    $this->travel(2)->seconds();
    expect(collect(pullAsTill($this->token, $cursor)->json('data.customers'))->pluck('name')->all())->toBe(['Just now']);
});

it('lists the staff who can work at this till, and sends the list again when a role changes', function () {
    $other = $this->tenant->run($this->a->business, function (): User {
        $user = User::factory()->memberOf($this->a->business)->create();
        $user->assignRole('cashier');
        $user->shops()->sync([$this->shop2->id]);

        return $user;
    });
    $this->travel(2)->seconds();

    $first = pullAsTill($this->token)->assertOk();
    $staff = collect($first->json('data.staff'))->pluck('uuid');
    expect($staff)->toContain((string) $this->a->owner->uuid)->not->toContain((string) $other->uuid);

    $this->tenant->run($this->a->business, fn () => $other->shops()->sync([$this->shop1->id, $this->shop2->id]));

    $second = pullAsTill($this->token, $first->json('meta.cursor'))->assertOk();
    expect(collect($second->json('data.staff'))->pluck('uuid'))->toContain((string) $other->uuid);
});

it('starts afresh from a cursor it can\'t read', function () {
    expect(pullAsTill($this->token, 'not-a-cursor')->assertOk()->json('data.products'))->not->toBeEmpty();
});

it('refuses a token that is not bound to a till', function () {
    pullAsTill(Till::signIn($this->a->owner))->assertForbidden()
        ->assertJsonPath('code', ApiErrorCode::RegisterTokenRequired->value);
});
