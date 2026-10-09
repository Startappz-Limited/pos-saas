<?php

use App\Models\BaileysChat;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('wa-gateway.url', 'https://gateway.test');
    config()->set('wa-gateway.token', 'test-token');
    config()->set('wa-gateway.webhook.secret', 'shh');

    $this->seed(PermissionSeeder::class);
    $user = User::factory()->create();
    $user->givePermissionTo(['baileys.view', 'baileys.send', 'baileys.manage']);
    $this->actingAs($user);

    Storage::fake('public');
    Http::fake([
        'gateway.test/sessions/*/messages/media' => Http::response(['wa_message_id' => 'WAID-MEDIA'], 200),
    ]);
});

it('uploads an image, stores it, and ships base64 to the bridge', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $chat = BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
    ]);

    $file = UploadedFile::fake()->image('photo.jpg', 200, 200);

    $this->postJson(route('baileys.inbox.sendMedia', $chat), [
        'file' => $file,
        'caption' => 'A pic',
    ])->assertOk()
        ->assertJsonPath('message.type', 'image')
        ->assertJsonPath('message.content', 'A pic');

    $msg = BaileysMessage::query()->where('baileys_chat_id', $chat->id)->latest('id')->first();
    expect($msg)->not->toBeNull()
        ->and($msg->media_url)->toContain('storage/baileys/'.$session->uuid);

    Http::assertSent(function ($r) {
        return str_contains($r->url(), '/messages/media')
            && $r['type'] === 'image'
            && ! empty($r['data']);
    });
});

it('classifies pdf as document', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $chat = BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
    ]);

    $file = UploadedFile::fake()->create('invoice.pdf', 50, 'application/pdf');

    $this->postJson(route('baileys.inbox.sendMedia', $chat), ['file' => $file])
        ->assertOk()
        ->assertJsonPath('message.type', 'document');
});

it('rejects requests without a file', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $chat = BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
    ]);

    $this->postJson(route('baileys.inbox.sendMedia', $chat), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');
});
