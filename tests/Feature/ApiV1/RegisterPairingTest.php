<?php

use App\Models\Register;
use App\Models\RegisterPairing;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * Register pairing, Phase 7 (step 2): a manager pairs this device to a till
 * and gets a token bound to the business and that register, which then acts
 * for the register's shop only. Re-pairing another device gives the register
 * a new code and signs the old device out. Cashiers sign in on a paired till
 * with its register and the device id.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    [$this->shop1, $this->shop2] = array_values($this->a->shops);

    [$this->register1, $this->register2, $this->manager] = $this->tenant->run($this->a->business, function (): array {
        $manager = User::factory()->memberOf($this->a->business)->create(['password' => bcrypt('password')]);
        $manager->assignRole('manager');
        $manager->shops()->sync([$this->shop1->id]);

        return [Register::defaultFor($this->shop1->id), Register::defaultFor($this->shop2->id), $manager];
    });
    $this->tenant->forget();
});

it('pairs a till and gives a token bound to the business and the register, with the last invoice number', function () {
    DB::table('registers')->where('id', $this->register1->id)->update(['last_invoice_number' => 41]);

    $response = Till::pair(Till::signIn($this->a->owner), $this->register1)->assertOk()
        ->assertJsonPath('data.register.uuid', $this->register1->uuid)
        ->assertJsonPath('data.register.code', 'R1')
        ->assertJsonPath('data.register.paired', true)
        ->assertJsonPath('data.last_invoice_number', 41)
        ->assertJsonPath('data.repaired', false);

    $tokenId = (int) explode('|', $response->json('data.token'))[0];
    expect(DB::table('personal_access_tokens')->where('id', $tokenId)->first(['business_id', 'register_id']))
        ->toEqual((object) ['business_id' => $this->a->business->id, 'register_id' => $this->register1->id])
        ->and($this->register1->fresh()->device_id)->toBe('device-1')
        ->and(DB::table('register_pairings')->where('register_id', $this->register1->id)->whereNull('ended_at')->count())->toBe(1)
        ->and($response->json('data.register'))->not->toHaveKey('device_id');
});

it('gives the same device a new token without changing anything else', function () {
    $owner = Till::signIn($this->a->owner);
    Till::pair($owner, $this->register1)->assertOk();

    Till::pair($owner, $this->register1)->assertOk()->assertJsonPath('data.repaired', false)->assertJsonPath('data.register.code', 'R1');

    expect(DB::table('register_pairings')->where('register_id', $this->register1->id)->count())->toBe(1);
});

it('gives the register a new code when another device takes it, and signs the old device out', function () {
    $owner = Till::signIn($this->a->owner);
    $oldDeviceToken = Till::pair($owner, $this->register1, 'old-install')->json('data.token');

    Till::pair($owner, $this->register1, 'new-install')->assertOk()
        ->assertJsonPath('data.repaired', true)
        ->assertJsonPath('data.previous_code', 'R1')
        ->assertJsonPath('data.register.code', 'R2');

    // The old installation's token no longer exists.
    Till::freshRequest();
    $this->withToken($oldDeviceToken)->getJson('/api/v1/registers')->assertUnauthorized();

    $pairings = DB::table('register_pairings')->where('register_id', $this->register1->id)->orderBy('id')->get();
    expect($pairings)->toHaveCount(2)
        ->and($pairings[0]->end_reason)->toBe(RegisterPairing::END_REPAIRED)
        ->and($pairings[0]->code)->toBe('R1')
        ->and($pairings[1]->code)->toBe('R2')
        ->and($pairings[1]->ended_at)->toBeNull();
});

it('never reuses a code, and keeps a re-paired default till as the shop\'s default', function () {
    $owner = Till::signIn($this->a->owner);
    Till::pair($owner, $this->register1, 'one');
    Till::pair($owner, $this->register1, 'two');
    Till::pair($owner, $this->register1, 'three')->assertJsonPath('data.register.code', 'R3');

    $default = $this->tenant->run($this->a->business, fn () => Register::defaultFor($this->shop1->id));

    expect($default->id)->toBe($this->register1->id)
        ->and(DB::table('registers')->where('shop_id', $this->shop1->id)->count())->toBe(1);
});

it('limits a register token to the register\'s shop, even for the owner', function () {
    $tillToken = Till::pair(Till::signIn($this->a->owner), $this->register1)->json('data.token');

    Till::freshRequest();
    $uuids = $this->withToken($tillToken)->getJson('/api/v1/registers')->assertOk()->json('data.*.uuid');

    expect($uuids)->toBe([$this->register1->uuid]);

    // The other shop's till can't be paired with it either.
    Till::pair($tillToken, $this->register2)->assertNotFound();
});

it('lets a manager pair a till of their shop only, and refuses a cashier', function () {
    $manager = Till::signIn($this->manager);
    Till::pair($manager, $this->register1)->assertOk();
    Till::pair($manager, $this->register2, 'device-2')->assertNotFound();

    $cashier = $this->a->cashier;
    $cashier->forceFill(['password' => bcrypt('password')])->save();
    $cashierToken = Till::signIn($cashier);
    Till::freshRequest();
    $this->withToken($cashierToken)->getJson('/api/v1/registers')->assertForbidden();
});

it('refuses another business\'s till', function () {
    $other = $this->tenant->run($this->fixture->b->business, fn () => Register::query()->first());
    $this->tenant->forget();

    Till::pair(Till::signIn($this->a->owner), $other)->assertNotFound();
    expect($other->fresh()->device_id)->toBeNull();
});

it('signs a cashier in on a paired till with a token bound to its register', function () {
    Till::pair(Till::signIn($this->a->owner), $this->register1, 'till-device');
    $cashier = $this->a->cashier;
    $cashier->forceFill(['password' => bcrypt('password')])->save();
    $this->tenant->run($this->a->business, fn () => $cashier->shops()->sync([$this->shop1->id]));
    Till::freshRequest();

    $signIn = fn (array $extra) => $this->postJson('/api/v1/auth/sign-in', [
        'email' => $cashier->email, 'password' => 'password', 'device_name' => 'Front till', 'register' => $this->register1->uuid, ...$extra,
    ]);

    $token = $signIn(['device_id' => 'till-device'])->assertOk()->json('data.token');
    expect(DB::table('personal_access_tokens')->where('id', (int) explode('|', $token)[0])->value('register_id'))->toBe($this->register1->id);

    // Another device, or a till that isn't paired, is refused before any token is made.
    $before = DB::table('personal_access_tokens')->count();
    $signIn(['device_id' => 'someone-else'])->assertUnprocessable()->assertJsonValidationErrors('device_id');
    // A till in a shop the cashier can't reach.
    $signIn(['register' => $this->register2->uuid, 'device_id' => 'till-device'])->assertUnprocessable()->assertJsonValidationErrors('register');
    expect(DB::table('personal_access_tokens')->count())->toBe($before);
});

it('records each pairing and its end in the audit trail', function () {
    $owner = Till::signIn($this->a->owner);
    Till::pair($owner, $this->register1, 'one');
    Till::pair($owner, $this->register1, 'two');

    $events = DB::connection('audit')->table('audit_logs')
        ->where('auditable_type', RegisterPairing::class)->where('business_id', $this->a->business->id)
        ->pluck('event')->all();

    expect($events)->toContain('created')->toContain('updated');
});
