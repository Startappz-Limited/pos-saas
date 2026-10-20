<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use App\Exceptions\FeatureUnavailable;
use App\Services\Billing\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a route whose feature the business's plan does not include (Phase 4):
 * `->middleware('feature:marketing')`. The same `Entitlements::hasFeature()`
 * check the policies, jobs and UI use. A business with no subscription is not
 * restricted.
 */
class RequireFeature
{
    public function __construct(private Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $case = Feature::from($feature);

        if (! $this->entitlements->hasFeature($case)) {
            throw new FeatureUnavailable($case);
        }

        return $next($request);
    }
}
