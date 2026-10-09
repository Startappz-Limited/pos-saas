<?php

use App\Models\BaileysChat;
use App\Models\BaileysSession;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo(['baileys.view', 'baileys.send', 'baileys.manage']);
    $this->actingAs($user);
});

it('creates a chat for a phone number from the inbox', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $response = $this->post(route('baileys.inbox.start', $session), [
        'phone' => '254712345678',
        'name' => 'Test Lead',
    ]);

    $chat = BaileysChat::query()
        ->where('baileys_session_id', $session->id)
        ->where('jid', '254712345678@s.whatsapp.net')
        ->first();

    expect($chat)->not->toBeNull()
        ->and($chat->name)->toBe('Test Lead')
        ->and($chat->shop_id)->toBe($session->shop_id);

    $response->assertRedirect(route('baileys.inbox.index', [
        'session' => $session->uuid,
        'chat' => $chat->id,
    ]));
});

it('reuses an existing chat for the same phone number', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $existing = BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
        'jid' => '254712345678@s.whatsapp.net',
        'name' => 'Original',
    ]);

    $this->post(route('baileys.inbox.start', $session), [
        'phone' => '+254 712 345 678',
    ])->assertRedirect();

    expect(BaileysChat::query()
        ->where('baileys_session_id', $session->id)
        ->where('jid', '254712345678@s.whatsapp.net')
        ->count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Original');
});

it('rejects an invalid phone number', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->from(route('baileys.inbox.index'))
        ->post(route('baileys.inbox.start', $session), ['phone' => 'abc'])
        ->assertRedirect(route('baileys.inbox.index'))
        ->assertSessionHasErrors('phone');

    expect(BaileysChat::query()->count())->toBe(0);
});
