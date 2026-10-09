<?php

use App\Enums\BaileysSessionStatus;
use App\Models\BaileysSession;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('wa-gateway.url', 'https://gateway.test');
    config()->set('wa-gateway.token', 'test-token');
    config()->set('wa-gateway.webhook.secret', 'shh');

    $this->seed(PermissionSeeder::class);
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['baileys.view', 'baileys.send', 'baileys.manage']);
    $this->actingAs($user);
});

it('lists baileys sessions', function () {
    BaileysSession::factory()->for(Shop::factory())->connected()->count(2)->create();

    $this->get(route('baileys.sessions.index'))
        ->assertOk()
        ->assertViewIs('baileys.sessions.index')
        ->assertViewHas('sessions');
});

it('renders the create form', function () {
    $this->get(route('baileys.sessions.create'))
        ->assertOk()
        ->assertViewIs('baileys.sessions.create');
});

it('starts a new baileys session on the gateway', function () {
    Http::fake([
        'gateway.test/sessions' => Http::response([
            'session_key' => 'ignored-here', 'status' => 'pending', 'qr_available' => false, 'qr_expires_at' => null,
        ], 201),
    ]);

    $shop = Shop::factory()->create();

    $this->post(route('baileys.sessions.store'), [
        'shop_id' => $shop->id,
        'name' => 'Reception',
    ])->assertRedirect();

    $session = BaileysSession::query()->where('shop_id', $shop->id)->first();
    expect($session)->not->toBeNull()
        ->and($session->name)->toBe('Reception')
        ->and($session->status)->toBe(BaileysSessionStatus::Pending);

    Http::assertSent(fn ($r) => $r->url() === 'https://gateway.test/sessions'
        && $r->hasHeader('Authorization', 'Bearer test-token')
        && $r['session_key'] === $session->session_key
        && $r['name'] === 'Reception');
});

it('records the gateway\'s reason when a session cannot be started', function () {
    Http::fake([
        'gateway.test/sessions' => Http::response(['error' => 'Missing scope: sessions:write'], 403),
    ]);

    $shop = Shop::factory()->create();

    $this->post(route('baileys.sessions.store'), ['shop_id' => $shop->id, 'name' => 'Reception']);

    $session = BaileysSession::query()->where('shop_id', $shop->id)->sole();
    expect($session->status)->toBe(BaileysSessionStatus::Failed)
        ->and($session->last_error)->toBe('Missing scope: sessions:write');
});

it('validates store input', function () {
    $this->from(route('baileys.sessions.create'))
        ->post(route('baileys.sessions.store'), ['shop_id' => 999_999])
        ->assertSessionHasErrors('shop_id');
});

it('shows a session detail page', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->get(route('baileys.sessions.show', $session))
        ->assertOk()
        ->assertViewIs('baileys.sessions.show')
        ->assertViewHas('session');
});

it('refreshes the QR for a pending session from the gateway', function () {
    Http::fake([
        'gateway.test/sessions/*/qr' => Http::response([
            'qr' => 'QR-DATA-XYZ',
            'expires_at' => now()->addMinutes(2)->toIso8601String(),
        ], 200),
    ]);

    $session = BaileysSession::factory()->for(Shop::factory())->create([
        'status' => BaileysSessionStatus::Pending,
        'qr_code' => null,
    ]);

    $this->get(route('baileys.sessions.show', $session))->assertOk();

    $session->refresh();
    expect($session->qr_code)->toBe('QR-DATA-XYZ')
        ->and($session->status)->toBe(BaileysSessionStatus::QrReady);
});

it('still shows a pending session when the gateway is unreachable', function () {
    Http::fake(['gateway.test/*' => Http::response('<html>502 Bad Gateway</html>', 502)]);
    config()->set('wa-gateway.retries', 0);

    $session = BaileysSession::factory()->for(Shop::factory())->create([
        'status' => BaileysSessionStatus::Pending,
        'qr_code' => null,
    ]);

    $this->get(route('baileys.sessions.show', $session))->assertOk();

    expect($session->fresh()->status)->toBe(BaileysSessionStatus::Pending);
});

it('disconnects a session', function () {
    Http::fake([
        'gateway.test/sessions/*' => Http::response(['deleted' => true], 200),
    ]);

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->delete(route('baileys.sessions.destroy', $session))
        ->assertRedirect(route('baileys.sessions.index'));

    expect($session->fresh()->status)->toBe(BaileysSessionStatus::Disconnected);
    Http::assertSent(fn ($r) => $r->method() === 'DELETE'
        && $r->url() === 'https://gateway.test/sessions/'.$session->session_key);
});

it('leaves a session linked, and says so, when the gateway cannot unlink it', function () {
    Http::fake(['gateway.test/*' => Http::response('<html>502 Bad Gateway</html>', 502)]);
    config()->set('wa-gateway.retries', 0);

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->from(route('baileys.sessions.index'))
        ->delete(route('baileys.sessions.destroy', $session))
        ->assertRedirect(route('baileys.sessions.index'))
        ->assertSessionHas('error');

    expect($session->fresh()->status)->toBe(BaileysSessionStatus::Connected);
});
