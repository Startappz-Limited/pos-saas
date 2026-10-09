<?php

use App\Enums\BaileysMessageDirection;
use App\Enums\BaileysMessageStatus;
use App\Enums\BaileysSessionStatus;
use App\Models\BaileysChat;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Shop;
use Startappz\WaGateway\Testing\InteractsWithWaGateway;

uses(InteractsWithWaGateway::class);

beforeEach(function () {
    config()->set('wa-gateway.webhook.secret', 'shh');
});

it('rejects webhooks with invalid signatures', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create();
    $body = json_encode(['event' => 'session.connected', 'data' => ['session_key' => $session->session_key]]);
    $timestamp = (string) now()->getTimestamp();

    $this->call('POST', route('wa-gateway.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_GATEWAY_TIMESTAMP' => $timestamp,
        'HTTP_X_BAILEYS_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$body, 'wrong'),
    ], $body)->assertUnauthorized();

    expect($session->fresh()->status)->not->toBe(BaileysSessionStatus::Connected);
});

it('marks session connected on session.connected event', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create([
        'status' => BaileysSessionStatus::Connecting,
    ]);

    $this->postWaGatewayWebhook('session.connected', [
        'session_key' => $session->session_key,
        'status' => 'connected',
        'jid' => '15551234567:4@s.whatsapp.net',
        'phone_number' => '15551234567',
        'display_name' => 'Shop Bot',
        'occurred_at' => now()->toIso8601String(),
    ])->assertOk();

    $session->refresh();
    expect($session->status)->toBe(BaileysSessionStatus::Connected)
        ->and($session->phone_number)->toBe('15551234567')
        ->and($session->jid)->toBe('15551234567:4@s.whatsapp.net');
});

it('persists inbound messages and creates the chat', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->postWaGatewayWebhook('message.received', [
        'session_key' => $session->session_key,
        'wa_message_id' => 'WA-IN-1',
        'chat_jid' => '15557777777@s.whatsapp.net',
        'chat_phone' => '15557777777',
        'sender_phone' => '15557777777',
        'sender_name' => 'Wanjiku',
        'direction' => 'inbound',
        'type' => 'text',
        'content' => 'Hello back',
        'timestamp' => 1777543200,
    ])->assertOk();

    $message = $session->messages()->where('wa_message_id', 'WA-IN-1')->first();

    expect($message)->not->toBeNull()
        ->and($message->content)->toBe('Hello back')
        ->and($message->direction)->toBe(BaileysMessageDirection::Inbound)
        ->and($message->sender_jid)->toBe('15557777777@s.whatsapp.net')
        ->and($message->sent_at->year)->toBe(2026);

    $chat = $session->chats()->where('jid', '15557777777@s.whatsapp.net')->first();
    expect($chat->last_message_preview)->toBe('Hello back')
        ->and($chat->unread_count)->toBe(1)
        ->and($chat->phone)->toBe('15557777777')
        ->and($chat->name)->toBe('Wanjiku');
});

it('stores a message sent from the phone itself as outbound, and not unread', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->postWaGatewayWebhook('message.received', [
        'session_key' => $session->session_key,
        'wa_message_id' => 'WA-OUT-1',
        'chat_jid' => '15557777777@s.whatsapp.net',
        'chat_phone' => '15557777777',
        'chat_name' => 'Wanjiku',
        'sender_name' => 'Shop Bot',
        'direction' => 'outbound',
        'type' => 'text',
        'content' => 'Your order is ready',
    ])->assertOk();

    $message = BaileysMessage::sole();
    $chat = BaileysChat::sole();

    expect($message->direction)->toBe(BaileysMessageDirection::Outbound)
        ->and($message->status)->toBe(BaileysMessageStatus::Sent)
        ->and($message->sender_jid)->toBe($session->jid)
        ->and($chat->unread_count)->toBe(0)
        ->and($chat->name)->toBe('Wanjiku');
});

it('ignores a message it already has, since delivery is at least once', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $data = [
        'session_key' => $session->session_key,
        'wa_message_id' => 'WA-DUP-1',
        'chat_jid' => '15557777777@s.whatsapp.net',
        'type' => 'text',
        'content' => 'Once',
    ];

    $this->postWaGatewayWebhook('message.received', $data)->assertOk();
    $this->postWaGatewayWebhook('message.received', $data)->assertOk(); // a new delivery of the same message

    expect(BaileysMessage::count())->toBe(1)
        ->and(BaileysChat::sole()->unread_count)->toBe(1);
});

it('stores nothing from a delivery that failed halfway, so the gateway\'s retry stores it whole', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $data = [
        'session_key' => $session->session_key,
        'wa_message_id' => 'WA-RETRY-1',
        'chat_jid' => '15557777777@s.whatsapp.net',
        'type' => 'text',
        'content' => 'Are you open today?',
    ];

    // The chat update fails once, after the message row was written.
    $failures = 1;
    BaileysChat::updating(function () use (&$failures) {
        if ($failures-- > 0) {
            throw new RuntimeException('database went away');
        }
    });

    $this->postWaGatewayWebhook('message.received', $data)->assertServerError();
    expect(BaileysMessage::count())->toBe(0);

    $this->postWaGatewayWebhook('message.received', $data)->assertOk(); // the gateway's retry

    expect(BaileysMessage::count())->toBe(1)
        ->and(BaileysChat::sole()->last_message_preview)->toBe('Are you open today?');
});

it('only moves a sent message forward, whatever order its receipts arrive in', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    $message = BaileysMessage::factory()->create([
        'baileys_session_id' => $session->id,
        'shop_id' => $session->shop_id,
        'wa_message_id' => 'WA-SENT-1',
        'direction' => BaileysMessageDirection::Outbound,
        'status' => BaileysMessageStatus::Sent,
    ]);

    $receipt = fn (string $status) => $this->postWaGatewayWebhook('message.status', [
        'session_key' => $session->session_key,
        'wa_message_id' => 'WA-SENT-1',
        'status' => $status,
    ])->assertOk();

    $receipt('read');
    $receipt('delivered'); // late
    $receipt('sent');      // later still

    expect($message->fresh()->status)->toBe(BaileysMessageStatus::Read);
});

it('acknowledges an event for a session it does not know, so the gateway does not dead-letter it', function () {
    $this->postWaGatewayWebhook('message.received', [
        'session_key' => 'shop-99-unknown',
        'wa_message_id' => 'WA-X',
        'chat_jid' => '15557777777@s.whatsapp.net',
        'type' => 'text',
        'content' => 'Hi',
    ])->assertOk();

    expect(BaileysMessage::count())->toBe(0);
});

it('shows a dropped connection as reconnecting, and a logout as disconnected', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->postWaGatewayWebhook('session.disconnected', [
        'session_key' => $session->session_key,
        'reason' => 'connection_closed',
        'occurred_at' => now()->addSecond()->toIso8601String(),
    ])->assertOk();
    expect($session->fresh()->status)->toBe(BaileysSessionStatus::Connecting);

    $this->postWaGatewayWebhook('session.disconnected', [
        'session_key' => $session->session_key,
        'reason' => 'logged_out',
        'occurred_at' => now()->addSeconds(2)->toIso8601String(),
    ])->assertOk();
    expect($session->fresh()->status)->toBe(BaileysSessionStatus::Disconnected)
        ->and($session->fresh()->last_error)->toContain('scan a new QR');
});

it('ignores a disconnect that arrives after the reconnect that followed it', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create([
        'status' => BaileysSessionStatus::Connecting,
    ]);
    $droppedAt = now()->subSeconds(10);

    // Delivered out of order: the reconnect lands first.
    $this->postWaGatewayWebhook('session.connected', [
        'session_key' => $session->session_key,
        'status' => 'connected',
        'jid' => '15551234567:4@s.whatsapp.net',
        'occurred_at' => $droppedAt->copy()->addSeconds(3)->toIso8601String(),
    ])->assertOk();
    $this->postWaGatewayWebhook('session.disconnected', [
        'session_key' => $session->session_key,
        'reason' => 'connection_closed',
        'occurred_at' => $droppedAt->toIso8601String(),
    ])->assertOk();

    expect($session->fresh()->status)->toBe(BaileysSessionStatus::Connected);
});

it('keeps the newest QR when an older one arrives late', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->create([
        'status' => BaileysSessionStatus::Pending,
    ]);

    $qr = fn (string $image, int $expiresIn) => $this->postWaGatewayWebhook('session.qr', [
        'session_key' => $session->session_key,
        'qr' => $image,
        'expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
        'occurred_at' => now()->toIso8601String(),
    ])->assertOk();

    $qr('data:image/png;base64,NEWER', 60);
    $qr('data:image/png;base64,OLDER', 20); // generated first, delivered second

    expect($session->fresh()->qr_code)->toBe('data:image/png;base64,NEWER');
});

it('does not put back a QR once the session has connected', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create([
        'connected_at' => now(),
    ]);

    $this->postWaGatewayWebhook('session.qr', [
        'session_key' => $session->session_key,
        'qr' => 'data:image/png;base64,OLD',
        'expires_at' => now()->addMinute()->toIso8601String(),
        'occurred_at' => now()->subSeconds(30)->toIso8601String(),
    ])->assertOk();

    expect($session->fresh()->status)->toBe(BaileysSessionStatus::Connected)
        ->and($session->fresh()->qr_code)->toBeNull();
});
