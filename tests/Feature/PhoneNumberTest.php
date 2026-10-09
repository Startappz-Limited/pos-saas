<?php

use App\Models\Customer;
use App\Models\Shop;
use App\Support\PhoneNumber;

describe('normalisation', function () {
    it('reduces every common Kenyan spelling to the same MSISDN', function (string $input) {
        expect(PhoneNumber::normalize($input))->toBe('254712345678');
    })->with([
        '0712345678',
        '0712 345 678',
        '+254712345678',
        '254712345678',
        '00254712345678',
        '712345678',
        '(0712) 345-678',
    ]);

    it('leaves a number that already carries another country code alone', function () {
        expect(PhoneNumber::normalize('255712345678'))->toBe('255712345678');
    });

    it('returns null for anything that is not a dialable number', function (?string $input) {
        expect(PhoneNumber::normalize($input))->toBeNull();
    })->with([
        null,
        '',
        '   ',
        '123',
        'abc',
        // A local number is 9 digits after the trunk zero. Anything else must be
        // rejected rather than turned into a plausible MSISDN that belongs to
        // nobody — the seeded "555-0101" numbers were becoming 254555010x.
        '555-0101',
        '0555 0101',
        '07123456789',
    ]);

    it('renders a number the way it is written locally', function () {
        expect(PhoneNumber::formatLocal('+254712345678'))->toBe('0712 345 678');
    });

    it('renders E.164 for anything that needs the plus', function () {
        expect(PhoneNumber::toE164('0712345678'))->toBe('+254712345678');
    });

    it('recognises two spellings of the same subscriber', function () {
        expect(PhoneNumber::matches('0712345678', '+254 712 345 678'))->toBeTrue()
            ->and(PhoneNumber::matches('0712345678', '0722000111'))->toBeFalse();
    });
});

describe('the backfill command', function () {
    it('changes nothing without --apply', function () {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->forShop($shop)->create(['phone' => '0712345678']);
        $customer->forceFill(['phone_normalized' => null])->saveQuietly();

        $this->artisan('customers:normalize-phones')->assertSuccessful();

        expect($customer->fresh()->phone_normalized)->toBeNull();
    });

    it('backfills the canonical number with --apply and leaves the typed one alone', function () {
        $shop = Shop::factory()->create();
        $customer = Customer::factory()->forShop($shop)->create(['phone' => '0712 345 678']);
        $customer->forceFill(['phone_normalized' => null])->saveQuietly();

        $this->artisan('customers:normalize-phones', ['--apply' => true])->assertSuccessful();

        expect($customer->fresh()->phone_normalized)->toBe('254712345678')
            ->and($customer->fresh()->phone)->toBe('0712 345 678');
    });
});
