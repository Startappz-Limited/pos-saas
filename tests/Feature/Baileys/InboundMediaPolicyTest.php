<?php

use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Shop;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Startappz\WaGateway\Webhooks\WebhookSignature;

beforeEach(function () {
    config()->set('wa-gateway.webhook.secret', 'shh');
    config()->set('baileys.media.store_inbound', true);
    config()->set('baileys.media.max_bytes', 1024);
    config()->set('baileys.media.chat_types', ['private']);
    config()->set('baileys.media.shop_quota_bytes', 0);
    config()->set('baileys.media.retention_days', 30);

    Storage::fake('public');
});

/** A signed gateway delivery of one inbound media message, each with its own delivery id. */
function postMediaWebhook(BaileysSession $session, string $chatJid, string $bytes, array $overrides = [])
{
    $payload = [
        'event' => 'message.received',
        'data' => array_merge([
            'session_key' => $session->session_key,
            'wa_message_id' => 'WA'.fake()->unique()->numerify('########'),
            'chat_jid' => $chatJid,
            'sender_phone' => '254712345678',
            'direction' => 'inbound',
            'type' => 'image',
            'content' => '',
            'media_mime' => 'image/jpeg',
            'media_data' => base64_encode($bytes),
        ], $overrides),
    ];

    $body = json_encode($payload);
    $timestamp = (string) now()->getTimestamp();

    return test()->call('POST', route('wa-gateway.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_GATEWAY_TIMESTAMP' => $timestamp,
        'HTTP_X_GATEWAY_DELIVERY' => (string) Str::uuid(),
        'HTTP_X_BAILEYS_SIGNATURE' => WebhookSignature::sign('shh', $timestamp, $body),
    ], $body);
}

it('stores media from a private chat and records its size', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create();

    postMediaWebhook($session, '254712345678@s.whatsapp.net', str_repeat('a', 500))
        ->assertOk();

    $message = BaileysMessage::sole();

    expect($message->media_url)->toContain('baileys/'.$session->uuid.'/in/')
        ->and($message->media_size)->toBe(500)
        ->and(Storage::disk('public')->allFiles('baileys/'.$session->uuid.'/in'))->toHaveCount(1);
});

it('records the message but drops media from status broadcasts', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create();

    postMediaWebhook($session, 'status@broadcast', str_repeat('a', 500))->assertOk();

    $message = BaileysMessage::sole();

    expect($message->media_url)->toBeNull()
        ->and($message->media_size)->toBeNull()
        ->and($message->payload['media_skipped'])->toBe('chat_type_not_allowed')
        ->and(Storage::disk('public')->allFiles('baileys'))->toBeEmpty();
});

it('drops media from group chats when groups are not an allowed chat type', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create();

    postMediaWebhook($session, '120363000000000000@g.us', str_repeat('a', 500))->assertOk();

    expect(BaileysMessage::sole()->payload['media_skipped'])->toBe('chat_type_not_allowed')
        ->and(Storage::disk('public')->allFiles('baileys'))->toBeEmpty();
});

it('drops media over the size cap', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create();

    postMediaWebhook($session, '254712345678@s.whatsapp.net', str_repeat('a', 2048))->assertOk();

    $message = BaileysMessage::sole();

    expect($message->media_url)->toBeNull()
        ->and($message->payload['media_skipped'])->toBe('too_large')
        ->and(Storage::disk('public')->allFiles('baileys'))->toBeEmpty();
});

it('drops media once the shop is over its quota', function () {
    config()->set('baileys.media.shop_quota_bytes', 600);

    $session = BaileysSession::factory()->for(Shop::factory())->create();

    postMediaWebhook($session, '254712345678@s.whatsapp.net', str_repeat('a', 500))->assertOk();
    postMediaWebhook($session, '254712345678@s.whatsapp.net', str_repeat('b', 500))->assertOk();

    $messages = BaileysMessage::orderBy('id')->get();

    expect($messages[0]->media_url)->not->toBeNull()
        ->and($messages[1]->media_url)->toBeNull()
        ->and($messages[1]->payload['media_skipped'])->toBe('quota_exceeded')
        ->and(Storage::disk('public')->allFiles('baileys/'.$session->uuid.'/in'))->toHaveCount(1);
});

it('keeps the message without a file when the gateway sends no bytes (media over 5 MB)', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create();

    postMediaWebhook($session, '254712345678@s.whatsapp.net', '', [
        'type' => 'video',
        'media_mime' => 'video/mp4',
        'media_data' => null,
    ])->assertOk();

    $message = BaileysMessage::sole();

    expect($message->type->value)->toBe('video')
        ->and($message->media_url)->toBeNull()
        ->and($message->media_mime)->toBe('video/mp4')
        ->and(Storage::disk('public')->allFiles('baileys'))->toBeEmpty();
});

it('prunes expired media files and clears their references', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create();

    postMediaWebhook($session, '254712345678@s.whatsapp.net', str_repeat('a', 500), [
        'content' => 'proof of payment',
    ])->assertOk();

    $message = BaileysMessage::sole();
    $message->forceFill(['created_at' => now()->subDays(45)])->save();

    $path = 'baileys/'.$session->uuid.'/in/'.basename(parse_url($message->media_url, PHP_URL_PATH));
    Storage::disk('public')->assertExists($path);

    // Dry run touches nothing.
    test()->artisan('baileys:prune-media --dry-run')->assertSuccessful();
    Storage::disk('public')->assertExists($path);
    expect($message->fresh()->media_url)->not->toBeNull();

    test()->artisan('baileys:prune-media')->assertSuccessful();

    Storage::disk('public')->assertMissing($path);
    expect($message->fresh()->media_url)->toBeNull()
        ->and($message->fresh()->media_size)->toBeNull()
        ->and($message->fresh()->content)->toBe('proof of payment');
});

it('deletes a session\'s media directory when the session is deleted', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create();

    postMediaWebhook($session, '254712345678@s.whatsapp.net', str_repeat('a', 500))->assertOk();

    expect(Storage::disk('public')->allFiles('baileys/'.$session->uuid))->toHaveCount(1);

    $session->delete();

    expect(Storage::disk('public')->allFiles('baileys/'.$session->uuid))->toBeEmpty();
});
