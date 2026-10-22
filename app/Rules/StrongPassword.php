<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * The password rules for signup, invite acceptance, password reset and the
 * owner's email change (Phase 6): 10 to 128 characters, not common, not
 * breached, not built from the person's own details, no trivial pattern. A
 * long passphrase passes; `Password123` does not.
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

        if (in_array($lower, self::common(), true)) {
            return 'That password is too common. Choose something harder to guess.';
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
            return 'That password has appeared in a data breach. Choose another.';
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

        $stem = preg_replace('/^[\d\W_]+|[\d\W_]+$/', '', $lower) ?? '';

        return $stem !== '' && $stem !== $lower && in_array($stem, self::common(), true);
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
    private static function common(): array
    {
        static $list = null;

        return $list ??= array_values(array_filter(array_map(
            'trim',
            file(database_path('data/common-passwords.txt')) ?: [],
        )));
    }
}
