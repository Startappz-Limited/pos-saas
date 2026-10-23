<?php

namespace App\Rules;

use App\Support\Signup\EmailPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A real address someone can read (Phase 6): not a placeholder or a
 * disposable provider, on a domain that accepts mail. The message never says
 * which test failed, so the blocklist can't be mapped by trial.
 */
class RealEmail implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $policy = app(EmailPolicy::class);

        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_EMAIL) || $policy->isPlaceholder($value)) {
            $fail(__(EmailPolicy::GENERIC_MESSAGE));

            return;
        }

        $domain = substr(strrchr($value, '@'), 1);

        if (! $policy->domainAcceptsMail($domain)) {
            $fail(__(EmailPolicy::GENERIC_MESSAGE));
        }
    }
}
