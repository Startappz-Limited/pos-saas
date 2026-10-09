<?php

use App\Models\BaileysChat;
use App\Models\BaileysSession;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['baileys.view', 'baileys.send']);
    $this->actingAs($user);
});

it('maps a @lid chat to a phone and name', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $chat = BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
        'jid' => '82579470418131@lid',
        'name' => null,
    ]);

    $this->post(route('baileys.inbox.map', $chat), [
        'phone' => '+254 712 345 678',
        'name' => 'Jane Doe',
    ])->assertRedirect();

    $chat->refresh();
    expect($chat->phone)->toBe('254712345678')
        ->and($chat->name)->toBe('Jane Doe')
        ->and($chat->jid)->toBe('82579470418131@lid');
});

it('rejects mapping with an invalid phone', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $chat = BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
        'jid' => '82579470418131@lid',
    ]);

    $this->from(route('baileys.inbox.index'))
        ->post(route('baileys.inbox.map', $chat), ['phone' => 'abc'])
        ->assertSessionHasErrors('phone');

    expect($chat->fresh()->phone)->toBeNull();
});
