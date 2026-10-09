<?php

use App\Actions\Baileys\SendBaileysMessage;
use App\Enums\BaileysSessionStatus;
use App\Models\BaileysChat;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

beforeEach(function () {
    config()->set('wa-gateway.url', 'https://gateway.test');
    config()->set('wa-gateway.token', 'test-token');
    config()->set('wa-gateway.webhook.secret', 'shh');
});

it('sends a text message via the gateway and persists it', function () {
    Http::fake([
        'gateway.test/sessions/*/messages/text' => Http::response([
            'wa_message_id' => 'WAID-1',
        ], 200),
    ]);

    $shop = Shop::factory()->create();
    $session = BaileysSession::factory()->for($shop)->connected()->create();

    $message = app(SendBaileysMessage::class)
        ->execute($session, '15551234567@s.whatsapp.net', 'Hello');

    expect($message)->toBeInstanceOf(BaileysMessage::class)
        ->and($message->wa_message_id)->toBe('WAID-1')
        ->and($message->status->value)->toBe('sent');

    expect(BaileysChat::where('jid', '15551234567@s.whatsapp.net')->exists())->toBeTrue();

    // The message's own uuid is its Idempotency-Key: a retried call is
    // answered from the gateway's record instead of sending twice.
    Http::assertSent(function ($request) use ($message) {
        return str_contains($request->url(), '/messages/text')
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->hasHeader('Idempotency-Key', $message->uuid)
            && $request['to'] === '15551234567@s.whatsapp.net'
            && $request['text'] === 'Hello';
    });
});

it('retries a gateway 5xx with the same Idempotency-Key, sending once', function () {
    Http::fake([
        'gateway.test/sessions/*/messages/text' => Http::sequence()
            ->push(['error' => 'Gateway error.'], 503)
            ->push(['wa_message_id' => 'WAID-2'], 200),
    ]);
    Sleep::fake();

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $message = app(SendBaileysMessage::class)->execute($session, '15551234567@s.whatsapp.net', 'Hello');

    $keys = Http::recorded()->map(fn ($pair) => $pair[0]->header('Idempotency-Key')[0] ?? null)->unique();
    expect($message->status->value)->toBe('sent')
        ->and($keys->all())->toBe([$message->uuid]);
});

it('records the gateway\'s reason on the message when the send is refused', function () {
    Http::fake([
        'gateway.test/sessions/*/messages/text' => Http::response(['error' => 'Session is not connected.'], 409),
    ]);

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $message = app(SendBaileysMessage::class)->execute($session, '15551234567@s.whatsapp.net', 'Hello');

    expect($message->status->value)->toBe('failed')
        ->and($message->error_message)->toBe('Session is not connected.')
        ->and($message->wa_message_id)->toBeNull();
});

it('refuses to send when the session is not connected', function () {
    $shop = Shop::factory()->create();
    $session = BaileysSession::factory()->for($shop)->create([
        'status' => BaileysSessionStatus::Pending,
    ]);

    expect(fn () => app(SendBaileysMessage::class)
        ->execute($session, '15551234567@s.whatsapp.net', 'Hi'))
        ->toThrow(RuntimeException::class);
});
