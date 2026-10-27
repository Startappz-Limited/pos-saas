<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Http\Responses\ApiV1;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sync endpoints act for one till (Phase 7): they take only a token bound to
 * a register (from pairing, or signing in on a paired till), which
 * ResolveTenant has already checked against the token's business.
 */
class EnsureRegisterToken
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->tenant->registerId() === null) {
            return ApiV1::error(ApiErrorCode::RegisterTokenRequired, __('Pair this device to a till, or sign in on a paired till, to sync.'), 403);
        }

        return $next($request);
    }
}
