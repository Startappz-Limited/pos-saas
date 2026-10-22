<?php

namespace App\Rules;

use App\Models\User;
use App\Support\Signup\EmailPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * One login per person (Phase 6): the address is compared in its normal form,
 * so `john+shop@gmail.com` and `j.ohn@gmail.com` are the same login as
 * `john@gmail.com`. Also what holds a second Free Trial back. Users carry no
 * tenant scope, so this sees every login.
 */
class UniqueLogin implements ValidationRule
{
    public function __construct(private ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $taken = User::query()
            ->when($this->ignoreUserId !== null, fn ($q) => $q->where('id', '!=', $this->ignoreUserId))
            ->where(fn ($q) => $q->where('email_normalized', EmailPolicy::normalise($value))
                ->orWhere('email', mb_strtolower($value)))
            ->exists();

        if ($taken) {
            $fail(__('An account with this email already exists. Sign in instead.'));
        }
    }
}
