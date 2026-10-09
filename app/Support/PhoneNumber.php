<?php

namespace App\Support;

/**
 * Canonicalises East African phone numbers to a bare MSISDN.
 *
 * A Kenyan number reaches the till in every shape a human can type it —
 * "0712 345 678", "+254 712 345678", "254712345678", "712345678" — and all four
 * are the same subscriber. Storing them verbatim means duplicate customers,
 * failed WhatsApp delivery and no reliable key to match a mobile-money payer on.
 *
 * The output is digits only, with no leading "+", e.g. "254712345678", which is
 * the format Safaricom's Daraja API and the Baileys bridge both expect.
 */
class PhoneNumber
{
    /**
     * Length of a subscriber number (without country code) in the default
     * country. Every Kenyan number — mobile and fixed — is 9 digits after the
     * trunk zero, so a local number of any other length is not a number.
     */
    private const LOCAL_SUBSCRIBER_LENGTH = 9;

    /**
     * Reduce a phone number to its canonical MSISDN, or null if it cannot be one.
     *
     * @param  string|null  $defaultCountryCode  Defaults to config('baileys.default_country_code').
     */
    public static function normalize(?string $phone, ?string $defaultCountryCode = null): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $countryCode = preg_replace('/\D+/', '', (string) (
            $defaultCountryCode ?? config('baileys.default_country_code', '254')
        )) ?? '';

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            // International access prefix: "00254712345678".
            $digits = ltrim(substr($digits, 2), '0');
        } elseif (str_starts_with($digits, '0')) {
            // National format: "0712345678" → country code + "712345678".
            $subscriber = ltrim($digits, '0');

            // The trunk zero only makes this a local number if what follows is
            // actually a subscriber number. Prefixing a country code onto a
            // 7-digit string would mint a plausible-looking MSISDN that belongs
            // to nobody, which is worse than admitting the number is unusable.
            if (strlen($subscriber) !== self::LOCAL_SUBSCRIBER_LENGTH) {
                return null;
            }

            $digits = $countryCode.$subscriber;
        } elseif ($countryCode !== '' && ! str_starts_with($digits, $countryCode)) {
            // A bare number is only treated as local at exactly the subscriber
            // length ("712345678"). Anything longer already carries some other
            // country's code — a Tanzanian "255…" must pass through untouched —
            // and anything shorter is a typo the length check below rejects.
            if (strlen($digits) === self::LOCAL_SUBSCRIBER_LENGTH) {
                $digits = $countryCode.$digits;
            }
        }

        // Shorter than any international number: a shortcode or a typo.
        if (strlen($digits) < 10) {
            return null;
        }

        return $digits;
    }

    /**
     * Render a number the way it is written locally: "0712 345 678".
     *
     * Falls back to the input when the number is not a recognisable local one,
     * so this is always safe to use for display.
     */
    public static function formatLocal(?string $phone, ?string $defaultCountryCode = null): ?string
    {
        $normalized = self::normalize($phone, $defaultCountryCode);

        if ($normalized === null) {
            return $phone;
        }

        $countryCode = preg_replace('/\D+/', '', (string) (
            $defaultCountryCode ?? config('baileys.default_country_code', '254')
        )) ?? '';

        if ($countryCode === '' || ! str_starts_with($normalized, $countryCode)) {
            return '+'.$normalized;
        }

        $subscriber = substr($normalized, strlen($countryCode));

        if (strlen($subscriber) !== self::LOCAL_SUBSCRIBER_LENGTH) {
            return '+'.$normalized;
        }

        return '0'.substr($subscriber, 0, 3).' '.substr($subscriber, 3, 3).' '.substr($subscriber, 6);
    }

    /**
     * Render in E.164 ("+254712345678") for anything that requires the plus.
     */
    public static function toE164(?string $phone, ?string $defaultCountryCode = null): ?string
    {
        $normalized = self::normalize($phone, $defaultCountryCode);

        return $normalized === null ? null : '+'.$normalized;
    }

    /**
     * Whether two numbers identify the same subscriber.
     */
    public static function matches(?string $a, ?string $b): bool
    {
        $left = self::normalize($a);

        return $left !== null && $left === self::normalize($b);
    }
}
