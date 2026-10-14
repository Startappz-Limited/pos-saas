<?php

namespace App\Support\Tenancy;

use RuntimeException;

/**
 * Tenant data was written or read with no business set: the code runs
 * outside a signed-in request (a job, command or webhook) and didn't set one.
 */
class MissingTenantContext extends RuntimeException
{
    public static function forModel(string $model): self
    {
        return new self("No business is set for this {$model}. Code that runs without a signed-in user must set one with TenantContext::run().");
    }
}
