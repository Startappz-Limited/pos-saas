<?php

use App\Enums\BaileysChatType;
use App\Enums\BaileysMessageStatus;
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

    $this->seed(PermissionSeeder::class);
    $user = User::factory()->owner()->create();
    $user->givePermissionTo(['baileys.view', 'baileys.send', 'baileys.manage']);
    $this->actingAs($user);
});

function privateChat(BaileysSession $session, array $attributes = []): BaileysChat
{
    return BaileysChat::factory()->for($session, 'session')->create([
        'shop_id' => $session->shop_id,
        ...$attributes,
    ]);
}

it('renders the broadcast page with the groups from the gateway', function () {
    Http::fake([
        'gateway.test/sessions/*/groups' => Http::response([
            'groups' => [['jid' => '12345-67890@g.us', 'name' => 'Team Sales', 'size' => 4]],
        ], 200),
    ]);

    BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->get(route('baileys.broadcast.show'))
        ->assertOk()
        ->assertViewIs('baileys.broadcast.show')
        ->assertSee('Team Sales')
        ->assertDontSee('Post to Channel');
});

it('still renders when the gateway cannot list groups, and says why', function () {
    Http::fake([
        'gateway.test/sessions/*/groups' => Http::response(['error' => 'Session is not connected.'], 409),
    ]);

    BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->get(route('baileys.broadcast.show'))
        ->assertOk()
        ->assertSee('Could not load groups from the WhatsApp gateway: Session is not connected.');
});

it('posts a message to a group via the gateway', function () {
    Http::fake([
        'gateway.test/sessions/*/messages/text' => Http::response(['wa_message_id' => 'GRP-1'], 200),
    ]);

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->post(route('baileys.broadcast.group', $session), [
        'jid' => '12345-67890@g.us',
        'text' => 'Hi team',
    ])->assertRedirect()->assertSessionHas('success');

    Http::assertSent(fn ($r) => str_contains($r->url(), '/messages/text')
        && $r['to'] === '12345-67890@g.us'
        && $r['text'] === 'Hi team');
});

it('says so when the gateway refuses a group post', function () {
    Http::fake([
        'gateway.test/sessions/*/messages/text' => Http::response(['error' => 'Rate limit exceeded.'], 429),
    ]);

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->post(route('baileys.broadcast.group', $session), [
        'jid' => '12345-67890@g.us',
        'text' => 'Hi team',
    ])->assertRedirect()->assertSessionHas('error', 'Not posted: Rate limit exceeded.');
});

it('rejects a non-group jid for the group endpoint', function () {
    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->from(route('baileys.broadcast.show'))
        ->post(route('baileys.broadcast.group', $session), [
            'jid' => '254712345678@s.whatsapp.net',
            'text' => 'nope',
        ])
        ->assertSessionHasErrors('jid');
});

it('posts a text status to the session\'s recent private chats', function () {
    Http::fake([
        'gateway.test/sessions/*/status' => Http::response(['wa_message_id' => 'ST-1', 'recipients' => 2], 200),
    ]);

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    privateChat($session, ['jid' => '254711111111@s.whatsapp.net', 'last_message_at' => now()->subMinute()]);
    privateChat($session, ['jid' => '98765432109876@lid', 'phone' => '0722222222', 'last_message_at' => now()]);
    privateChat($session, ['jid' => '55555555555555@lid', 'phone' => null]); // no number to send to
    // Not a person, even with a number mapped onto it from the inbox.
    privateChat($session, ['jid' => '120363000000000000@g.us', 'type' => BaileysChatType::Group, 'phone' => '0733333333']);

    $this->post(route('baileys.broadcast.status', $session), [
        'type' => 'text',
        'text' => 'Hello status',
        'background_color' => '#1E88E5',
    ])->assertRedirect()->assertSessionHas('success', 'Status posted to 2 of your recent WhatsApp contacts.');

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/sessions/'.$session->session_key.'/status')
        && $r['type'] === 'text'
        && $r['text'] === 'Hello status'
        && $r['background_color'] === '#1E88E5'
        && $r['recipients'] === ['254722222222@s.whatsapp.net', '254711111111@s.whatsapp.net']);

    $status = BaileysMessage::sole();
    expect($status->chat_jid)->toBe('status@broadcast')
        ->and($status->status)->toBe(BaileysMessageStatus::Sent)
        ->and($status->payload['recipient_count'])->toBe(2);
});

it('posts an image status with the text as its caption and no background colour', function () {
    Http::fake([
        'gateway.test/sessions/*/status' => Http::response(['wa_message_id' => 'ST-2', 'recipients' => 1], 200),
    ]);

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    privateChat($session, ['jid' => '254711111111@s.whatsapp.net']);

    $this->post(route('baileys.broadcast.status', $session), [
        'type' => 'image',
        'url' => 'https://example.test/promo.jpg',
        'text' => '20% off this week',
        'background_color' => '#075E54',
    ])->assertRedirect()->assertSessionHas('success');

    Http::assertSent(fn ($r) => $r['type'] === 'image'
        && $r['url'] === 'https://example.test/promo.jpg'
        && $r['caption'] === '20% off this week'
        && ! isset($r['background_color']));
});

it('caps the audience at the configured number of recipients', function () {
    config()->set('baileys.status_max_recipients', 2);
    Http::fake([
        'gateway.test/sessions/*/status' => Http::response(['wa_message_id' => 'ST-3', 'recipients' => 2], 200),
    ]);

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();
    foreach (range(1, 4) as $i) {
        privateChat($session, ['jid' => "25471111111{$i}@s.whatsapp.net", 'last_message_at' => now()->subMinutes($i)]);
    }

    $this->post(route('baileys.broadcast.status', $session), ['type' => 'text', 'text' => 'Hi']);

    Http::assertSent(fn ($r) => $r['recipients'] === ['254711111111@s.whatsapp.net', '254711111112@s.whatsapp.net']);
});

it('refuses a status nobody could see, without calling the gateway', function () {
    Http::fake();

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->post(route('baileys.broadcast.status', $session), ['type' => 'text', 'text' => 'Hello?'])
        ->assertRedirect()
        ->assertSessionHas('error');

    Http::assertNothingSent();
    expect(BaileysMessage::sole()->status)->toBe(BaileysMessageStatus::Failed);
});

it('rejects a background colour the gateway would refuse', function () {
    Http::fake();

    $session = BaileysSession::factory()->for(Shop::factory())->connected()->create();

    $this->from(route('baileys.broadcast.show'))
        ->post(route('baileys.broadcast.status', $session), [
            'type' => 'text',
            'text' => 'Hello',
            'background_color' => 'blue',
        ])
        ->assertSessionHasErrors('background_color');

    Http::assertNothingSent();
});
