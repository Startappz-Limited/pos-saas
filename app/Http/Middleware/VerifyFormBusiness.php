<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One active business per session (decision 4.9, stage 3). Every form the
 * pages render carries the business it was loaded for (the `@csrf` directive
 * adds the `_business` field; scripts may send `X-Business` instead). A
 * submission for a business that is no longer the active one is refused, so
 * a form from before a switch can never save into the other business.
 *
 * Submissions that name no business (older pages, webhooks, sign-in forms) are
 * not judged here: their own routes still run inside the active business.
 */
class VerifyFormBusiness
{
    public const FIELD = '_business';

    public const HEADER = 'X-Business';

    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! $request->user()) {
            return $next($request);
        }

        $loadedFor = $request->input(self::FIELD) ?? $request->header(self::HEADER);
        $active = $this->tenant->business()?->uuid;

        if (is_string($loadedFor) && $loadedFor !== '' && $loadedFor !== $active) {
            $message = __('This business was switched; reload the page.');

            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $message], 409)
                : back()->with('error', $message);
        }

        return $next($request);
    }
}
