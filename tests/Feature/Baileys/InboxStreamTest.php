<?php

use App\Models\BaileysChat;
use App\Models\BaileysSession;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['baileys.view']);
    $this->actingAs($user);
});

it('returns a streaming SSE response for an authorised user', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $chat = BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
    ]);

    $response = $this->call('GET', route('baileys.inbox.stream', $chat));

    expect($response->headers->get('Content-Type'))->toContain('text/event-stream');
    expect($response->headers->get('X-Accel-Buffering'))->toBe('no');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
});
