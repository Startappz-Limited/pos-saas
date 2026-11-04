<?php

use App\Actions\Sync\WarnOverdueTills;
use App\Models\AuditLog;
use App\Models\Register;
use App\Notifications\TillSyncOverdue;
use App\Support\Sync\Dispositions;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/*
 * Push results the till can act on (stage 10, step 5c): every result that
 * isn't applied carries one `disposition` (retry, drop, hold); "push the other
 * item first" is `pending` with a `*_not_synced` code; an app before this
 * stage still gets Phase 7's codes. The till reports its unsynced items and
 * its app version, and re-pairing can't lose unsynced work unnoticed.
 */

beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    config(['sync.min_version.dispositions' => '2.0.0']);
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->tenant = app(TenantContext::class);
    $this->register = $this->tenant->run($this->a->business, fn () => Register::defaultFor($this->a->shops[0]->id));
    $this->tenant->forget();
    $this->token = Till::token($this->a->owner, $this->register);
    $this->travel(1)->hours();
    $this->push = function (array $item, ?string $version = '2.0.0', array $extra = []) {
        Till::freshRequest();

        // Headers stay on the test between calls: an app with no version sends none.
        $version === null ? $this->withoutHeader('X-App-Version') : $this->withHeader('X-App-Version', $version);

        return $this->withToken($this->token)
            ->postJson('/api/v1/sync/push', ['items' => [['client_uuid' => (string) Str::uuid(), 'recorded_at' => now()->toDateTimeString(), 'data' => [], ...$item]], ...$extra]);
    };
});

it('gives every code exactly the class the Contract table gives it', function (string $status, ?string $code, ?string $class) {
    expect(Dispositions::for($status, $code))->toBe($class);
})->with([
    ['applied', null, null], ['duplicate', null, null], ['pending', 'sale_not_synced', 'retry'], ['failed', 'server_error', 'retry'],
    ['refused', 'subscription_inactive', 'retry'], ['refused', 'void_window_closed', 'drop'], ['refused', 'sale_has_returns', 'drop'],
    ['refused', 'forbidden', 'drop'], ['refused', 'sale_already_paid', 'drop'], ['refused', 'outside_trading_lease', 'hold'],
    ['refused', 'invalid_invoice_number', 'hold'], ['refused', 'invoice_number_taken', 'hold'], ['refused', 'validation_failed', 'hold'],
    ['refused', 'unknown_item_type', 'hold'], ['refused', 'something_new', 'hold'],
]);

it('answers pending with retry when an item comes before what it names', function (array $item, string $code) {
    ($this->push)($item)->assertOk()
        ->assertJsonPath('data.results.0.status', 'pending')
        ->assertJsonPath('data.results.0.code', $code)
        ->assertJsonPath('data.results.0.disposition', 'retry');
})->with([
    'a payment before its sale' => [['type' => 'sale_payment', 'data' => ['sale_client_uuid' => (string) Str::uuid(), 'amount' => 10, 'payment_method' => 'cash']], 'sale_not_synced'],
    'a session close before its opening' => [['type' => 'cash_session', 'data' => ['action' => 'close', 'session' => (string) Str::uuid(), 'closing_balance' => 0]], 'cash_session_not_synced'],
]);

it('holds an unknown item type, and gives an app before this stage Phase 7\'s codes with no disposition', function () {
    ($this->push)(['type' => 'something_new'])->assertJsonPath('data.results.0.disposition', 'hold');

    $old = ($this->push)(['type' => 'sale_payment', 'data' => ['sale_client_uuid' => (string) Str::uuid(), 'amount' => 10, 'payment_method' => 'cash']], null)->json('data.results.0');
    expect($old['status'])->toBe('refused')
        ->and($old['code'])->toBe('not_found')
        ->and($old)->not->toHaveKey('disposition');
    expect(($this->push)(['type' => 'sale_payment', 'data' => ['sale_client_uuid' => (string) Str::uuid(), 'amount' => 10, 'payment_method' => 'cash']], '1.9.0')->json('data.results.0.code'))->toBe('not_found');
});

it('stores the till\'s unsynced count and app version from a push and a pull, and shows them in the register list', function () {
    ($this->push)(['type' => 'something_new'], '2.1.0', ['outbox_count' => 41])->assertOk();
    $this->tenant->run($this->a->business, fn () => expect($this->register->fresh()->only(['outbox_count', 'app_version']))->toBe(['outbox_count' => 41, 'app_version' => '2.1.0']));

    Till::freshRequest();
    $this->withToken($this->token)->withHeaders(['X-App-Version' => '2.2.0'])->getJson('/api/v1/sync/pull?outbox_count=3')->assertOk();
    Till::freshRequest();
    $list = collect($this->withToken($this->token)->getJson('/api/v1/registers')->assertOk()->json('data'))->firstWhere('uuid', $this->register->uuid);
    expect($list['outbox_count'])->toBe(3)->and($list['app_version'])->toBe('2.2.0');
});

it('names the unsynced count in the overdue warning', function () {
    Notification::fake();
    ($this->push)(['type' => 'something_new'], '2.0.0', ['outbox_count' => 41]);
    $this->travel(30)->hours();

    app(WarnOverdueTills::class)->execute();

    Notification::assertSentTo($this->a->owner, TillSyncOverdue::class, fn (TillSyncOverdue $notice) => str_contains(collect($notice->toMail($this->a->owner)->introLines)->implode(' '), 'It holds 41 unsynced item(s).'));
});

it('refuses re-pairing a till whose device last reported unsynced items unless confirmed, audits the confirmed one, and doesn\'t ask at zero', function () {
    ($this->push)(['type' => 'something_new'], '2.0.0', ['outbox_count' => 5]);
    $this->tenant->forget();
    $signIn = Till::signIn($this->a->owner);

    Till::freshRequest();
    $this->withToken($signIn)->postJson("/api/v1/registers/{$this->register->uuid}/pair", ['device_id' => 'device-2'])
        ->assertUnprocessable()->assertJsonPath('code', 'till_has_unsynced_items')->assertJsonPath('unsynced_items', 5);
    Till::freshRequest();
    $this->withToken($signIn)->postJson("/api/v1/registers/{$this->register->uuid}/pair", ['device_id' => 'device-2', 'confirm' => true])->assertOk()
        ->assertJsonPath('data.repaired', true);
    expect(AuditLog::query()->where('auditable_type', Register::class)->get()->contains(fn ($row) => ($row->new_values['repaired_with_unsynced_items'] ?? null) === 5))->toBeTrue();

    // The new device reported nothing: the next re-pair doesn't ask.
    Till::freshRequest();
    $this->withToken($signIn)->postJson("/api/v1/registers/{$this->register->uuid}/pair", ['device_id' => 'device-3'])->assertOk();
});
