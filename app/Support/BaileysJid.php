<?php

namespace App\Support;

class BaileysJid
{
    /**
     * Convert a phone number (any common format) to a WhatsApp JID.
     *
     * Normalisation rules (default country code from config('baileys.default_country_code'), e.g. "254"):
     *   - "0714587511"   → "254714587511" (strip leading 0, prepend country code)
     *   - "714587511"    → "254714587511" (local mobile, prepend country code)
     *   - "254714587511" → "254714587511" (already international, untouched)
     *   - "+254714587511"/"00254714587511" → "254714587511"
     */
    public static function fromPhone(string $phone, ?string $defaultCountryCode = null): ?string
    {
        $countryCode = $defaultCountryCode ?? (string) config('baileys.default_country_code', '254');
        $countryCode = preg_replace('/\D+/', '', $countryCode) ?? '';

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // International prefix "00…" → drop the 00.
        if (str_starts_with($digits, '00')) {
            $digits = ltrim(substr($digits, 2), '0');
        } elseif (str_starts_with($digits, '0')) {
            // Local format "0XXXXXXXXX" → strip leading 0 and prepend country code.
            $digits = $countryCode . ltrim($digits, '0');
        } elseif ($countryCode !== '' && ! str_starts_with($digits, $countryCode)) {
            // Bare local number (e.g. "714587511") that does not yet contain the country code.
            // Heuristic: anything shorter than a typical international number (12 digits)
            // is treated as local and prefixed with the configured country code.
            if (strlen($digits) < 11) {
                $digits = $countryCode . $digits;
            }
        }

        if ($digits === '') {
            return null;
        }

        return $digits . '@s.whatsapp.net';
    }
}
