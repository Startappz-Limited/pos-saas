<?php

use App\Actions\User\ManageTillPin;
use App\Models\Register;
use App\Models\TillPin;
use App\Rules\ValidTillPin;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/**
 * Till PINs, Phase 7 (step 5b): each person sets a PIN per business for
 * signing in on an offline till; the till receives its hash with the staff
 * list. Setting and resetting are audited without the hash. Product images
 * reach the till as full URLs.
 */
beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    $this->tenant->forget();
});

function tillPinAuditRows(int $businessId): array
{
    return DB::connection('audit')->table('audit_logs')
        ->where('auditable_type', TillPin::class)->where('business_id', $businessId)
        ->orderBy('id')->get(['event', 'old_values', 'new_values'])->all();
}

it('refuses short, repeated, running and common PINs, and accepts a sound one', function (string $pin, bool $passes) {
    expect(Validator::make(['pin' => $pin], ['pin' => [new ValidTillPin]])->passes())->toBe($passes);
})->with([
    ['123', false], ['1234567', false], ['12a4', false],
    ['1111', false], ['1234', false], ['4321', false], ['890123', false], ['2580', false],
    ['4071', true], ['583920', true],
]);

it('lets a person set their own PIN with their password, storing only its hash, and audits it without the hash', function () {
    $owner = $this->a->owner;

    $this->actingAs($owner)->put(route('profile.till-pin.update'), ['current_password' => 'password', 'pin' => '4071', 'pin_confirmation' => '4071'])
        ->assertRedirect(route('profile.edit'));

    $stored = $this->tenant->run($this->a->business, fn () => TillPin::query()->where('user_id', $owner->id)->firstOrFail());
    expect(Hash::check('4071', $stored->pin_hash))->toBeTrue()
        ->and($stored->pin_hash)->not->toContain('4071');

    $audit = tillPinAuditRows($this->a->business->id);
    expect($audit)->toHaveCount(1)
        ->and($audit[0]->event)->toBe('created')
        ->and((string) $audit[0]->new_values)->not->toContain('pin_hash')->not->toContain($stored->pin_hash);
});

it('refuses a wrong password, a mismatched repeat and an easy PIN', function () {
    $this->actingAs($this->a->owner);

    $this->put(route('profile.till-pin.update'), ['current_password' => 'wrong', 'pin' => '4071', 'pin_confirmation' => '4071'])->assertSessionHasErrors('current_password');
    $this->put(route('profile.till-pin.update'), ['current_password' => 'password', 'pin' => '4071', 'pin_confirmation' => '4072'])->assertSessionHasErrors('pin');
    $this->put(route('profile.till-pin.update'), ['current_password' => 'password', 'pin' => '1234', 'pin_confirmation' => '1234'])->assertSessionHasErrors('pin');

    expect($this->tenant->run($this->a->business, fn () => TillPin::query()->count()))->toBe(0);
});

it('shows the PIN card on the profile and the reset button to those who may edit the person', function () {
    $this->actingAs($this->a->owner)->get(route('profile.edit'))->assertOk()->assertSee('Till PIN')->assertSee('No PIN yet');
    $this->actingAs($this->a->owner)->get(route('users.show', $this->a->cashier))->assertOk()->assertSee('Reset till PIN');
});

it('lets whoever may edit a person reset their PIN, audited, and refuses others', function () {
    $cashier = $this->a->cashier;
    $this->actingAs($cashier)->put(route('profile.till-pin.update'), ['current_password' => 'password', 'pin' => '583920', 'pin_confirmation' => '583920']);
    $this->tenant->forget();

    // A cashier can't reset the owner's.
    $this->actingAs($cashier)->delete(route('users.till-pin.reset', $this->a->owner))->assertForbidden();

    $this->actingAs($this->a->owner)->delete(route('users.till-pin.reset', $cashier))->assertRedirect();

    expect($this->tenant->run($this->a->business, fn () => TillPin::query()->where('user_id', $cashier->id)->exists()))->toBeFalse()
        ->and(collect(tillPinAuditRows($this->a->business->id))->pluck('event')->all())->toBe(['created', 'deleted']);
});

it('sets and resets PINs on /api/v1, and refuses another business\'s person', function () {
    $owner = Till::signIn($this->a->owner);

    Till::freshRequest();
    $this->withToken($owner)->putJson('/api/v1/me/till-pin', ['current_password' => 'password', 'pin' => '4071', 'pin_confirmation' => '4071'])
        ->assertOk()->assertJsonStructure(['data' => ['set_at']]);

    Till::freshRequest();
    $this->withToken($owner)->deleteJson("/api/v1/staff/{$this->fixture->b->owner->uuid}/till-pin")->assertNotFound();

    Till::freshRequest();
    $this->withToken($owner)->deleteJson("/api/v1/staff/{$this->a->owner->uuid}/till-pin")->assertOk()->assertJsonPath('data.reset', true);
});

it('gives the till each person\'s PIN hash with the staff list, and sends it again when a PIN changes', function () {
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $register = $this->tenant->run($this->a->business, fn () => Register::query()->orderBy('id')->firstOrFail());
    $this->tenant->forget();
    $token = Till::token($this->a->owner, $register);
    $this->travel(2)->seconds();

    Till::freshRequest();
    $first = $this->withToken($token)->getJson('/api/v1/sync/pull')->assertOk();
    $ownerRow = collect($first->json('data.staff'))->firstWhere('uuid', (string) $this->a->owner->uuid);
    expect($ownerRow['pin_hash'])->toBeNull();

    $this->tenant->run($this->a->business, fn () => app(ManageTillPin::class)->set($this->a->owner, '4071'));

    Till::freshRequest();
    $second = $this->withToken($token)->getJson('/api/v1/sync/pull?since='.$first->json('meta.cursor'))->assertOk();
    $ownerRow = collect($second->json('data.staff'))->firstWhere('uuid', (string) $this->a->owner->uuid);

    expect(Hash::check('4071', $ownerRow['pin_hash']))->toBeTrue()
        ->and($ownerRow['pin_set_at'])->not->toBeNull();
});

it('sends product images to the till as full URLs', function () {
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $register = $this->tenant->run($this->a->business, function () {
        $this->a->product->forceFill(['image' => 'some-business/products/tea.jpg'])->save();

        return Register::query()->orderBy('id')->firstOrFail();
    });
    $this->tenant->forget();
    $token = Till::token($this->a->owner, $register);
    $this->travel(2)->seconds();

    Till::freshRequest();
    $product = collect($this->withToken($token)->getJson('/api/v1/sync/pull')->json('data.products'))
        ->firstWhere('uuid', (string) $this->a->product->uuid);

    expect($product['image_url'])->toBe(rtrim(config('filesystems.disks.public.url'), '/').'/some-business/products/tea.jpg')
        ->and($product['image_url'])->toStartWith('http')
        ->and(TenantFiles::publicUrl('https://shop.example.co.ke/tea.png'))->toBe('https://shop.example.co.ke/tea.png')
        ->and(TenantFiles::publicUrl(null))->toBeNull();
});

it('keeps each business\'s PIN apart', function () {
    $both = $this->fixture->both;
    $this->tenant->run($this->a->business, fn () => app(ManageTillPin::class)->set($both, '4071'));

    expect($this->tenant->run($this->fixture->b->business, fn () => TillPin::query()->where('user_id', $both->id)->exists()))->toBeFalse();
});
