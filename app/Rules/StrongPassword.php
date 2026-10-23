<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The one password rule, wherever a password is set (Phase 6;
 * fix/invite-first-sign-in): signup, invite acceptance, password reset,
 * changing your own password, and an admin setting one on the web or the API.
 * 10 to 128 characters, not a common or leaked password, not built from the
 * person's own details, no trivial pattern. A long passphrase passes;
 * `Password123`, `P@ssw0rd1` and `Welcome2024!` do not.
 *
 * Two layers against leaked passwords: an offline list of the 10,000 most
 * common ones (database/data/common-passwords.txt, see its README), checked
 * also with letter swaps undone and leading or trailing digits and symbols
 * removed; then Have I Been Pwned, which fails open, so an outside service
 * being down never blocks anyone (the offline list still applies).
 */
class StrongPassword implements DataAwareRule, ValidationRule
{
    public const MIN = 10;

    public const MAX = 128;

    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  list<string>  $personal  words the password must not contain (name, business, email)
     */
    public function __construct(private array $personal = []) {}

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('The :attribute must be a string.', ['attribute' => $attribute]));

            return;
        }

        $length = mb_strlen($value);

        if ($length < self::MIN || $length > self::MAX) {
            $fail(__('The :attribute must be between :min and :max characters.', ['attribute' => $attribute, 'min' => self::MIN, 'max' => self::MAX]));

            return;
        }

        $reason = $this->weakness($value);

        if ($reason !== null) {
            $fail(__($reason));
        }
    }

    private function weakness(string $password): ?string
    {
        $lower = Str::lower($password);

        if ($this->isCommon($lower)) {
            return 'This password has appeared in data leaks, so it\'s one of the first that attackers try. Choose another; a few random words work well.';
        }

        foreach ($this->personalWords() as $word) {
            if (str_contains($lower, $word)) {
                return 'The password must not contain your name, email or business name.';
            }
        }

        if ($this->isTrivial($lower)) {
            return 'That password follows a pattern that is easy to guess. Try a longer phrase.';
        }

        if ($this->isBreached($password)) {
            return 'This password has appeared in data leaks, so it\'s one of the first that attackers try. Choose another; a few random words work well.';
        }

        return null;
    }

    /**
     * Words from the person's own details, at least 3 characters.
     *
     * @return list<string>
     */
    private function personalWords(): array
    {
        $sources = array_merge($this->personal, [
            (string) ($this->data['name'] ?? ''),
            (string) ($this->data['business_name'] ?? ''),
            Str::before((string) ($this->data['email'] ?? ''), '@'),
        ]);

        $words = [];
        foreach ($sources as $source) {
            $source = Str::lower($source);
            $words[] = preg_replace('/[^a-z0-9]/', '', $source);
            foreach (preg_split('/[^a-z0-9]+/', $source) ?: [] as $part) {
                $words[] = $part;
            }
        }

        return array_values(array_unique(array_filter($words, fn ($w) => is_string($w) && strlen($w) >= 3)));
    }

    /**
     * Repeated characters, runs (12345678, qwertyui) and a common word with
     * digits, symbols or a year around it (Welcome2024, Admin@123).
     */
    private function isTrivial(string $lower): bool
    {
        if (preg_match('/^(.)\1+$/u', $lower) === 1) {
            return true;
        }

        $alnum = preg_replace('/[^a-z0-9]/', '', $lower) ?? '';

        if (strlen($alnum) >= 6 && $this->isRun($alnum)) {
            return true;
        }

        return false;
    }

    /**
     * Whether the password is a common (leaked) one, also once letter swaps
     * are undone (`p@ssw0rd`) and leading or trailing digits and symbols
     * removed (`Password123!`).
     */
    private function isCommon(string $lower): bool
    {
        $common = self::common();

        foreach ($this->variants($lower) as $variant) {
            if ($variant !== '' && isset($common[$variant])) {
                return true;
            }
        }

        return false;
    }

    /**
     * $lower as typed, with common letter swaps undone (1 read as i and as l),
     * and each with leading or trailing digits and symbols removed.
     *
     * @return list<string>
     */
    private function variants(string $lower): array
    {
        $swaps = ['@' => 'a', '4' => 'a', '0' => 'o', '3' => 'e', '$' => 's', '5' => 's', '7' => 't', '!' => 'i', '|' => 'l', '+' => 't'];
        $forms = [$lower, strtr($lower, $swaps + ['1' => 'i']), strtr($lower, $swaps + ['1' => 'l'])];

        foreach ($forms as $form) {
            $forms[] = preg_replace('/^[\d\W_]+|[\d\W_]+$/', '', $form) ?? '';
        }

        // Undo swaps only inside the word: trailing digits stay digits ("p@ssw0rd123").
        foreach ([$lower] as $form) {
            $core = preg_replace('/[\d\W_]+$/', '', $form) ?? '';
            $forms[] = strtr($core, $swaps + ['1' => 'i']);
            $forms[] = strtr($core, $swaps + ['1' => 'l']);
        }

        return array_values(array_unique($forms));
    }

    private function isRun(string $s): bool
    {
        foreach (['0123456789', 'abcdefghijklmnopqrstuvwxyz', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm'] as $run) {
            if (str_contains($run, $s) || str_contains(strrev($run), $s)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Have I Been Pwned range API (only the first 5 hash characters leave
     * the server). A failure lets the password through: the local list is
     * the fallback.
     */
    private function isBreached(string $password): bool
    {
        if (! config('security.signup.breach_check')) {
            return false;
        }

        $hash = Str::upper(sha1($password));

        try {
            $response = Http::timeout(3)->withHeaders(['Add-Padding' => 'true'])
                ->get('https://api.pwnedpasswords.com/range/'.substr($hash, 0, 5));

            if (! $response->successful()) {
                return false;
            }

            foreach (explode("\n", $response->body()) as $line) {
                [$suffix, $count] = array_pad(explode(':', trim($line)), 2, '0');

                if ($suffix === substr($hash, 5) && (int) $count > 0) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    /**
     * The common passwords, as a set (lowercase password => true).
     *
     * @return array<string, true>
     */
    private static function common(): array
    {
        static $set = null;

        return $set ??= array_fill_keys(array_values(array_filter(array_map(
            'trim',
            file(database_path('data/common-passwords.txt')) ?: [],
        ))), true);
    }
}
