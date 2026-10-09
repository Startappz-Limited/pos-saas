<?php

use App\Support\BaileysJid;

uses(Tests\TestCase::class);

it('normalises local Kenyan numbers to international JID', function (string $input, string $expected) {
    config()->set('baileys.default_country_code', '254');

    expect(BaileysJid::fromPhone($input))->toBe($expected . '@s.whatsapp.net');
})->with([
    'leading zero' => ['0714587511', '254714587511'],
    'bare local mobile' => ['714587511', '254714587511'],
    'already international' => ['254714587511', '254714587511'],
    'plus prefix' => ['+254714587511', '254714587511'],
    'double-zero prefix' => ['00254714587511', '254714587511'],
    'spaces and dashes' => ['0714-587 511', '254714587511'],
]);

it('returns null for empty input', function () {
    expect(BaileysJid::fromPhone(''))->toBeNull()
        ->and(BaileysJid::fromPhone('---'))->toBeNull();
});

it('respects an explicit country code argument', function () {
    expect(BaileysJid::fromPhone('0712345678', '255'))
        ->toBe('255712345678@s.whatsapp.net');
});
