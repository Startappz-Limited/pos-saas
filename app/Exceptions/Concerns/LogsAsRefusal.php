<?php

namespace App\Exceptions\Concerns;

use App\Support\Logging\RequestContext;
use Illuminate\Support\Facades\Log;

/**
 * For exceptions that refuse a request on purpose (a business rule, a
 * read-only business, a plan limit…): logged at `notice` with who, where and
 * why, rather than as an error with a stack trace. They are expected, but a
 * record of them helps answer "why couldn't I…?".
 */
trait LogsAsRefusal
{
    public function report(): void
    {
        Log::notice('Request refused: '.class_basename($this), [
            'reason' => $this->getMessage(),
            ...RequestContext::toArray(),
        ]);
    }
}
