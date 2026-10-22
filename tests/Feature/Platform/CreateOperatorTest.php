<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

/**
 * `platform:create-operator`, Phase 5 (B4, decision 4.7): creates a platform
 * operator outside every business. Never a business member: a member's email
 * is refused; an existing login with no membership is promoted only on
 * request.
 */
beforeEach(function () {
    migrateAuditDatabase();
});

const STRONG_PASSWORD = 'Operat0r!Strong-pass';

it('creates a platform operator outside every business', function () {
    $this->artisan('platform:create-operator', ['--name' => 'Platform Ops', '--email' => 'Ops@Platform.test'])
        ->expectsQuestion('Password (at least 12 characters, with letters, numbers and a symbol)', STRONG_PASSWORD)
        ->expectsQuestion('Password again', STRONG_PASSWORD)
        ->expectsOutputToContain('Platform operator ops@platform.test is ready.')
        ->assertSuccessful();

    $operator = User::query()->where('email', 'ops@platform.test')->firstOrFail();

    expect($operator->isPlatformOperator())->toBeTrue()
        ->and($operator->memberships()->count())->toBe(0)
        ->and($operator->default_business_id)->toBeNull()
        ->and(DB::table('model_has_roles')->where('model_id', $operator->id)->where('model_type', User::class)->count())->toBe(0)
        ->and($operator->email_verified_at)->not->toBeNull()
        ->and($operator->status->value ?? $operator->status)->toBe('active')
        ->and(Hash::check(STRONG_PASSWORD, $operator->password))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('anything.at-all'))->toBeTrue();

    $audit = AuditLog::query()->where('auditable_type', User::class)->where('auditable_id', $operator->id)->where('event', 'created')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->business_id)->toBeNull()
        ->and(json_encode($audit->new_values))->not->toContain(STRONG_PASSWORD);
});

it('generates a password on request and prints it once', function () {
    Artisan::call('platform:create-operator', ['--name' => 'Ops', '--email' => 'gen@platform.test', '--generate-password' => true]);

    preg_match('/Password \(shown once, store it safely\): (\S+)/', Artisan::output(), $printed);
    $operator = User::query()->where('email', 'gen@platform.test')->firstOrFail();

    expect($printed[1] ?? null)->not->toBeNull()
        ->and(strlen($printed[1]))->toBe(24)
        ->and(Hash::check($printed[1], $operator->password))->toBeTrue();
});

it('refuses the email of a business member', function () {
    $member = User::factory()->memberOf(Business::factory()->create())->create(['email' => 'cashier@shop.test']);

    $this->artisan('platform:create-operator', ['--name' => 'Ops', '--email' => 'cashier@shop.test', '--generate-password' => true])
        ->expectsOutputToContain('A platform operator is never a business member')
        ->assertFailed();
    $this->artisan('platform:create-operator', ['--name' => 'Ops', '--email' => 'cashier@shop.test', '--promote' => true])->assertFailed();

    expect($member->fresh()->isPlatformOperator())->toBeFalse();
});

it('promotes an existing login with no membership only when asked, keeping its password', function () {
    $login = User::factory()->create(['email' => 'loner@platform.test', 'password' => Hash::make('kept-password')]);
    $login->memberships()->delete();

    $this->artisan('platform:create-operator', ['--name' => 'Loner', '--email' => 'loner@platform.test', '--generate-password' => true])
        ->expectsOutputToContain('Run again with --promote')
        ->assertFailed();
    expect($login->fresh()->isPlatformOperator())->toBeFalse();

    $this->artisan('platform:create-operator', ['--name' => 'Loner', '--email' => 'loner@platform.test', '--promote' => true])->assertSuccessful();

    expect($login->fresh()->isPlatformOperator())->toBeTrue()
        ->and(Hash::check('kept-password', $login->fresh()->password))->toBeTrue();
});

it('refuses a weak password, a mismatch and a bad email', function () {
    $this->artisan('platform:create-operator', ['--name' => 'Ops', '--email' => 'weak@platform.test'])
        ->expectsQuestion('Password (at least 12 characters, with letters, numbers and a symbol)', 'short')
        ->assertFailed();
    $this->artisan('platform:create-operator', ['--name' => 'Ops', '--email' => 'weak@platform.test'])
        ->expectsQuestion('Password (at least 12 characters, with letters, numbers and a symbol)', STRONG_PASSWORD)
        ->expectsQuestion('Password again', STRONG_PASSWORD.'x')
        ->expectsOutputToContain('The passwords don\'t match.')
        ->assertFailed();
    $this->artisan('platform:create-operator', ['--name' => 'Ops', '--email' => 'not-an-email', '--generate-password' => true])->assertFailed();

    expect(User::query()->whereIn('email', ['weak@platform.test', 'not-an-email'])->exists())->toBeFalse();
});

it('never lets the operator flag be set through mass assignment', function () {
    $user = User::create(['name' => 'Sneaky', 'email' => 'sneaky@platform.test', 'password' => 'x', 'is_platform_operator' => true]);

    expect($user->fresh()->isPlatformOperator())->toBeFalse();
});
