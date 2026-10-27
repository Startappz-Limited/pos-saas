<?php

use App\Enums\ApiErrorCode;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SyncIssue;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * Offline rules, Phase 7 (step 8, decision 4.9): a till's invoice series is
 * checked on push (gaps recorded and closed as numbers arrive, repeats
 * recorded though refused); what a till recorded before a lapse is accepted
 * after it, the rest kept on the till; possible duplicate customers, sales
 * below zero and future-dated sales wait on a review screen where an owner or
 * manager keeps, merges or checks them.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    $this->register = $this->tenant->run($this->a->business, fn () => Register::defaultFor($this->a->product->shop_id));
    $this->tenant->forget();
});

function rulesPush(string $token, array $items)
{
    Till::freshRequest();

    return test()->withToken($token)->postJson('/api/v1/sync/push', ['items' => $items]);
}

function rulesSale(?string $invoice = null, string $at = '2026-10-26 10:00:00', array $overrides = []): array
{
    return ['client_uuid' => (string) Str::uuid(), 'type' => 'sale', 'recorded_at' => $at, 'data' => [
        'source_id' => DB::table('sale_sources')->where('business_id', test()->a->business->id)->value('id'),
        'delivery_location' => 'In store', 'walk_in_customer_name' => 'Walk-in', 'walk_in_customer_phone' => '0700000000',
        'payment_method' => 'cash',
        'items' => [['product_id' => test()->a->product->id, 'quantity' => 1, 'price' => 100]],
        ...($invoice ? ['invoice_number' => $invoice] : []),
        ...$overrides,
    ]];
}

function rulesInvoice(int $number): string
{
    $shop = DB::table('shops')->where('id', test()->register->shop_id)->value('code');
    $code = DB::table('registers')->where('id', test()->register->id)->value('code');

    return 'INV-'.strtoupper($shop).'-'.$code.'-2026-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
}

function issuesOfA(): Collection
{
    return app(TenantContext::class)->run(test()->a->business, fn () => SyncIssue::query()->orderBy('id')->get());
}

it('records a gap in a till\'s invoice series and closes it as the missing numbers arrive', function () {
    $token = Till::token($this->a->owner, $this->register);
    $this->travel(2)->hours();

    rulesPush($token, [rulesSale(rulesInvoice(1)), rulesSale(rulesInvoice(4))])->assertOk();
    $gap = issuesOfA()->firstWhere('type', SyncIssue::INVOICE_GAP);
    expect($gap->details['missing'])->toBe([2, 3])->and($gap->resolved_at)->toBeNull();

    rulesPush($token, [rulesSale(rulesInvoice(2))]);
    expect($gap->fresh()->details['missing'])->toBe([3]);

    rulesPush($token, [rulesSale(rulesInvoice(3))]);
    expect($gap->fresh()->resolution)->toBe('filled')->and($gap->fresh()->resolved_at)->not->toBeNull();
});

it('records an invoice number used twice for review, though the second sale is refused', function () {
    $token = Till::token($this->a->owner, $this->register);
    $this->travel(2)->hours();

    rulesPush($token, [rulesSale(rulesInvoice(1))]);
    rulesPush($token, [rulesSale(rulesInvoice(1))])->assertJsonPath('data.results.0.code', ApiErrorCode::InvoiceNumberTaken->value);

    $repeat = issuesOfA()->firstWhere('type', SyncIssue::INVOICE_REPEAT);
    expect($repeat->key)->toBe(rulesInvoice(1))
        ->and($repeat->details['existing_sale'])->not->toBeNull();
});

it('accepts after a lapse what the till recorded before it, keeps the rest on the till, and still audits sign-ins', function () {
    subscribe($this->a->business, 'tier-2', ['current_period_end' => Carbon::parse('2026-10-27 09:00:00')]);
    $token = Till::token($this->a->owner, $this->register);
    $this->travelTo(Carbon::parse('2026-10-27 12:00:00'));

    $results = rulesPush($token, [
        rulesSale(null, '2026-10-26 15:00:00'),
        rulesSale(null, '2026-10-27 10:00:00'),
        ['client_uuid' => (string) Str::uuid(), 'type' => 'till_sign_in', 'recorded_at' => '2026-10-27 10:05:00', 'data' => ['user' => (string) $this->a->owner->uuid, 'outcome' => 'signed_in']],
    ])->assertOk()->json('data.results');

    expect(array_column($results, 'status'))->toBe(['applied', 'refused', 'applied'])
        ->and($results[1]['code'])->toBe(ApiErrorCode::SubscriptionInactive->value);
});

it('refuses every push but sign-ins while the business has more shops than it pays for', function () {
    $token = Till::token($this->a->owner, $this->register);
    // Read-only from here: pairing itself is refused while over the shops paid for.
    subscribe($this->a->business, 'tier-2', ['shop_count' => 1]);
    $this->travel(2)->hours();

    rulesPush($token, [rulesSale()])->assertJsonPath('data.results.0.code', ApiErrorCode::SubscriptionInactive->value);
});

it('puts a possible duplicate on the review screen, where merging moves its sales and drops it from every till', function () {
    $token = Till::token($this->a->owner, $this->register);
    $this->travel(2)->hours();
    $existing = $this->a->customer;
    $phone = $this->tenant->run($this->a->business, fn () => $existing->phone);
    $twin = ['client_uuid' => (string) Str::uuid(), 'type' => 'customer', 'recorded_at' => '2026-10-26 10:00:00', 'data' => ['name' => 'Twin', 'phone' => $phone]];
    $sale = rulesSale(null, '2026-10-26 10:05:00', ['customer_client_uuid' => $twin['client_uuid']]);
    rulesPush($token, [$twin, $sale])->assertOk();

    $issue = issuesOfA()->firstWhere('type', SyncIssue::POSSIBLE_DUPLICATE_CUSTOMER);
    $this->tenant->forget();
    $this->actingAs($this->a->owner)->get(route('sync-issues.index'))->assertOk()->assertSee('Possible duplicate customer')->assertSee('Twin');

    $this->actingAs($this->a->owner)->post(route('sync-issues.merge', $issue))->assertRedirect();

    $twinRow = $this->tenant->run($this->a->business, fn () => Customer::query()->withTrashed()->where('client_uuid', $twin['client_uuid'])->first());
    expect($twinRow->trashed())->toBeTrue()
        ->and($twinRow->merged_into_id)->toBe($existing->id)
        ->and($this->tenant->run($this->a->business, fn () => Sale::query()->where('client_uuid', $sale['client_uuid'])->value('customer_id')))->toBe($existing->id)
        ->and($issue->fresh()->resolution)->toBe('merged');

    // A sale the till still makes for the merged customer reaches the one kept.
    $late = rulesSale(null, '2026-10-26 10:30:00', ['customer_client_uuid' => $twin['client_uuid']]);
    rulesPush($token, [$late])->assertJsonPath('data.results.0.status', 'applied');
    expect($this->tenant->run($this->a->business, fn () => Sale::query()->where('client_uuid', $late['client_uuid'])->value('customer_id')))->toBe($existing->id);

    // Every till is told to drop it.
    $this->travel(2)->seconds();
    Till::freshRequest();
    expect(collect($this->withToken($token)->getJson('/api/v1/sync/pull')->json('data.deletions'))->pluck('uuid'))->toContain((string) $twinRow->uuid);
});

it('keeps both customers when asked, and won\'t merge one with a credit account', function () {
    $token = Till::token($this->a->owner, $this->register);
    $this->travel(2)->hours();
    $phone = $this->tenant->run($this->a->business, fn () => $this->a->customer->phone);
    rulesPush($token, [
        ['client_uuid' => $first = (string) Str::uuid(), 'type' => 'customer', 'recorded_at' => '2026-10-26 10:00:00', 'data' => ['name' => 'One', 'phone' => $phone]],
        ['client_uuid' => (string) Str::uuid(), 'type' => 'customer', 'recorded_at' => '2026-10-26 10:01:00', 'data' => ['name' => 'Two', 'phone' => $phone]],
    ]);
    [$one, $two] = issuesOfA()->where('type', SyncIssue::POSSIBLE_DUPLICATE_CUSTOMER)->values()->all();
    $this->tenant->forget();

    $this->actingAs($this->a->owner)->post(route('sync-issues.resolve', $one))->assertRedirect();
    expect($one->fresh()->resolution)->toBe('kept');

    $this->tenant->run($this->a->business, function () use ($two) {
        $customer = Customer::query()->where('uuid', $two->details['customer'])->firstOrFail();
        CreditAccount::factory()->create(['customer_id' => $customer->id]);
    });
    $this->tenant->forget();
    $this->actingAs($this->a->owner)->post(route('sync-issues.merge', $two))->assertSessionHas('error');
    expect($two->fresh()->resolved_at)->toBeNull();
});

it('records sales below zero stock and future-dated sales, which can be marked as checked, audited', function () {
    $token = Till::token($this->a->owner, $this->register);
    $this->travel(2)->hours();

    rulesPush($token, [
        rulesSale(null, '2026-10-26 10:00:00', ['items' => [['product_id' => $this->a->product->id, 'quantity' => 9999, 'price' => 1]]]),
        rulesSale(null, '2026-10-27 09:00:00'),
    ])->assertOk();

    $types = issuesOfA()->pluck('type')->all();
    expect($types)->toContain(SyncIssue::NEGATIVE_STOCK)->toContain(SyncIssue::IMPLAUSIBLE_TIME);

    $issue = issuesOfA()->firstWhere('type', SyncIssue::NEGATIVE_STOCK);
    $this->tenant->forget();
    $this->actingAs($this->a->owner)->post(route('sync-issues.resolve', $issue))->assertRedirect();

    expect($issue->fresh()->resolution)->toBe('checked')
        ->and(DB::connection('audit')->table('audit_logs')->where('auditable_type', SyncIssue::class)->where('event', 'updated')->exists())->toBeTrue();
});

it('shows the review screen to owners and managers only, and never another business\'s issues', function () {
    $token = Till::token($this->a->owner, $this->register);
    $this->travel(2)->hours();
    rulesPush($token, [rulesSale(null, '2026-10-26 10:00:00', ['items' => [['product_id' => $this->a->product->id, 'quantity' => 9999, 'price' => 1]]])]);
    $issue = issuesOfA()->first();
    $this->tenant->forget();

    $this->actingAs($this->a->cashier)->get(route('sync-issues.index'))->assertForbidden();

    $manager = $this->tenant->run($this->a->business, function (): User {
        $user = User::factory()->memberOf($this->a->business)->create();
        $user->assignRole('manager');

        return $user;
    });
    $this->tenant->forget();
    $this->actingAs($manager)->get(route('sync-issues.index'))->assertOk();

    $this->actingAs($this->fixture->b->owner)->get(route('sync-issues.index'))->assertOk()->assertDontSee('Sold below zero stock');
    $this->actingAs($this->fixture->b->owner)->post(route('sync-issues.resolve', $issue))->assertNotFound();
});
