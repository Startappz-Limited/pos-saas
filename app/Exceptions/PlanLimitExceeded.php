<?php

namespace App\Exceptions;

use App\Enums\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Adding something past what the plan allows (Phase 4). Rendered as a 402
 * `plan_limit_reached` on JSON requests and as a message on the web.
 */
class PlanLimitExceeded extends RuntimeException
{
    public function __construct(public readonly Limit $limit, public readonly int $allowed)
    {
        parent::__construct(__('Your plan allows :allowed :what. Upgrade or add shops to your plan to add more.', [
            'allowed' => $allowed,
            'what' => $limit->label(),
        ]));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'code' => 'plan_limit_reached',
                'message' => $this->getMessage(),
                'limit' => $this->limit->value,
                'allowed' => $this->allowed,
            ], 402);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
