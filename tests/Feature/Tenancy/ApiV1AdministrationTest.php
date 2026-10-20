<?php

use App\Contracts\MpesaGateway;
use App\Models\Product;
use App\Models\User;
use App\Notifications\OwnerConfirmationCode;
use App\Services\Mpesa\FakeMpesaGateway;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TwoBusinesses;

/**
 * The business-administration flows on /api/v1 (stage 3): the same rules as
 * the web screens, `{success, message, data}` responses, tokens bound to one
 * business.
 */
beforeEach(function () {
    if (! Schema::connection('audit')->hasTable('audit_logs')) {
        Artisan::call('migrate', ['--database' => 'audit', '--path' => 'database/migrations/audit', '--force' => true]);
    }
    Storage::fake('public');
    Notification::fake();
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->b = $this->fixture->b;
    $this->a->business->update(['primary_owner_id' => $this->a->owner->id]);
    app()->instance(MpesaGateway::class, new FakeMpesaGateway);
});

function v1As(User $user, $business): void
{
    app(TenantContext::class)->forget();
    $token = $user->createToken('test', ['*']);
    $token->accessToken->forceFill(['business_id' => $business->id])->save();
    test()->withToken($token->plainTextToken);
}

it('reads and updates the settings of the token\'s business only', function () {
    v1As($this->a->owner, $this->a->business);

    $this->postJson('/api/v1/business/settings', [
        'name' => 'Acme', 'currency' => 'UGX', 'timezone' => 'Africa/Kampala', 'invoice_prefix' => 'acme',
    ])->assertOk()->assertJsonPath('data.currency', 'UGX')->assertJsonPath('data.invoice_prefix', 'ACME');

    $this->getJson('/api/v1/business/settings')->assertOk()->assertJsonPath('data.name', 'Acme');

    expect($this->b->business->fresh()->currency)->not->toBe('UGX');
});

it('refuses settings to someone without the permission', function () {
    v1As($this->a->cashier, $this->a->business);

    $this->getJson('/api/v1/business/settings')->assertForbidden();
});

it('saves a messaging account without ever returning it', function () {
    v1As($this->a->owner, $this->a->business);

    $this->putJson('/api/v1/business/integrations/sms', ['api_url' => 'https://sms.acme.test/send', 'api_token' => 'secret-token', 'sender_id' => 'ACME'])
        ->assertOk()->assertJsonPath('data.connected', true);

    $response = $this->getJson('/api/v1/business/integrations')->assertOk()->assertJsonPath('data.sms.connected', true)->assertJsonPath('data.whatsapp_cloud.connected', false);
    expect($response->getContent())->not->toContain('secret-token');

    $this->deleteJson('/api/v1/business/integrations/sms')->assertOk()->assertJsonPath('data.connected', false);
    $this->putJson('/api/v1/business/integrations/telegram', [])->assertNotFound();
});

it('proves a shop\'s M-Pesa number and refuses another business\'s shop', function () {
    v1As($this->a->owner, $this->a->business);

    $this->putJson("/api/v1/shops/{$this->a->shops[0]->uuid}/mpesa", ['shortcode' => '888999', 'type' => 'till', 'consumer_key' => 'k', 'consumer_secret' => 's'])
        ->assertOk()->assertJsonPath('data.shortcode', '888999');
    $this->putJson("/api/v1/shops/{$this->b->shops[0]->uuid}/mpesa", ['shortcode' => '111222', 'type' => 'till', 'consumer_key' => 'k', 'consumer_secret' => 's'])
        ->assertNotFound();
});

it('imports products, or lists the errors and imports nothing', function () {
    v1As($this->a->owner, $this->a->business);

    $this->post('/api/v1/products/import', [
        'shop' => (string) $this->a->shops[0]->uuid,
        'file' => UploadedFile::fake()->createWithContent('p.csv', "name,selling_price\nOne,5\nTwo,x\n"),
    ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('success', false);

    $this->post('/api/v1/products/import', [
        'shop' => (string) $this->a->shops[0]->uuid,
        'file' => UploadedFile::fake()->createWithContent('p.csv', "name,selling_price\nOne,5\nTwo,6\n"),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.imported', 2);

    expect(app(TenantContext::class)->run($this->a->business, fn () => Product::query()->whereIn('name', ['One', 'Two'])->count()))->toBe(2);
});

it('lists the onboarding steps', function () {
    v1As($this->a->owner, $this->a->business);

    $this->getJson('/api/v1/business/onboarding')->assertOk()->assertJsonCount(5, 'data.steps');
});

it('hands over with an emailed code through the API', function () {
    $coOwner = User::factory()->memberOf($this->a->business)->create();
    app(TenantContext::class)->run($this->a->business, fn () => $coOwner->assignRole('admin'));

    v1As($this->a->owner, $this->a->business);
    $this->postJson('/api/v1/business/handover/code', ['owner' => $coOwner->uuid])->assertOk();

    $code = null;
    Notification::assertSentTo($this->a->owner, OwnerConfirmationCode::class, function (OwnerConfirmationCode $n) use (&$code): bool {
        $code = $n->code();

        return true;
    });

    $this->postJson('/api/v1/business/handover', ['code' => '000000'])->assertStatus(422);
    $this->postJson('/api/v1/business/handover', ['code' => $code])->assertOk()->assertJsonPath('data.owner', (string) $coOwner->uuid);

    expect($this->a->business->fresh()->primary_owner_id)->toBe($coOwner->id);
});

it('refuses a handover to someone who is not a co-owner', function () {
    v1As($this->a->owner, $this->a->business);

    $this->postJson('/api/v1/business/handover/code', ['owner' => $this->b->owner->uuid])->assertStatus(422);
    Notification::assertNothingSent();
});

it('closes the business with the code and ends the token', function () {
    v1As($this->a->owner, $this->a->business);
    $this->postJson('/api/v1/business/closure/code')->assertOk();

    $code = null;
    Notification::assertSentTo($this->a->owner, OwnerConfirmationCode::class, function (OwnerConfirmationCode $n) use (&$code): bool {
        $code = $n->code();

        return true;
    });

    $this->postJson('/api/v1/business/closure', ['code' => $code])->assertOk()->assertJsonStructure(['data' => ['erase_after']]);

    expect($this->a->business->fresh()->status)->toBe('closing');
});

it('lets only owners start a closure', function () {
    v1As($this->a->cashier, $this->a->business);

    $this->postJson('/api/v1/business/closure/code')->assertForbidden();
    $this->postJson('/api/v1/business/exports')->assertForbidden();
});
