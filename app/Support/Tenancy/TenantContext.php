<?php

namespace App\Support\Tenancy;

use App\Models\Business;
use Closure;

/**
 * The business the current request, job or command acts for (decision 4.3).
 *
 * Set by ResolveTenant for signed-in requests (the session's business on the
 * web, the user's default business for API tokens), and explicitly by code
 * that runs without a signed-in user: webhooks, jobs, commands, seeders.
 * Bound as a scoped singleton, so it never outlives a request or a job.
 */
class TenantContext
{
    private ?Business $business = null;

    private int $bypassDepth = 0;

    public function set(Business|int|null $business): void
    {
        $this->business = is_int($business) ? Business::findOrFail($business) : $business;
    }

    public function forget(): void
    {
        $this->business = null;
    }

    public function business(): ?Business
    {
        return $this->business;
    }

    public function id(): ?int
    {
        return $this->business?->id;
    }

    public function has(): bool
    {
        return $this->business !== null;
    }

    /**
     * Runs $callback for $business, then restores the previous business.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function run(Business|int $business, Closure $callback): mixed
    {
        $previous = $this->business;
        $this->set($business);

        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }

    /**
     * Runs $callback with tenant scoping switched off: for platform code that
     * deliberately works across businesses, such as resolving which business a
     * webhook belongs to. Every caller is listed in an architecture test.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function bypass(Closure $callback): mixed
    {
        $this->bypassDepth++;

        try {
            return $callback();
        } finally {
            $this->bypassDepth--;
        }
    }

    public function bypassing(): bool
    {
        return $this->bypassDepth > 0;
    }
}
