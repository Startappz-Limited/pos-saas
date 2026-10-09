<?php

use App\Models\BaileysChat;
use App\Models\BaileysMessage;
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

    Http::fake([
        'gateway.test/sessions/*/messages/text' => Http::response(['wa_message_id' => 'WAID-X'], 200),
    ]);
});

it('sends a text message and redirects on a normal request', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $chat = BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
    ]);

    $this->post(route('baileys.inbox.send', $chat), ['text' => 'Hello world'])
        ->assertRedirect();

    expect(BaileysMessage::query()->where('baileys_chat_id', $chat->id)->count())->toBe(1);
});

it('returns JSON when the request expects JSON', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $chat = BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
    ]);

    $this->postJson(route('baileys.inbox.send', $chat), ['text' => 'Yo'])
        ->assertOk()
        ->assertJson(['ok' => true])
        ->assertJsonPath('message.content', 'Yo')
        ->assertJsonPath('message.direction', 'outbound');
});

it('rejects empty messages', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $chat = BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
    ]);

    $this->from(route('baileys.inbox.index'))
        ->post(route('baileys.inbox.send', $chat), ['text' => ''])
        ->assertSessionHasErrors('text');
});
