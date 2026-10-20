<?php

namespace App\Exceptions;

use App\Enums\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A feature the business's plan does not include (Phase 4). Rendered as a 402
 * `feature_unavailable` on JSON requests.
 */
class FeatureUnavailable extends RuntimeException
{
    public function __construct(public readonly Feature $feature)
    {
        parent::__construct(__('Your plan does not include this feature. Upgrade to use it.'));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'code' => 'feature_unavailable',
                'message' => $this->getMessage(),
                'feature' => $this->feature->value,
            ], 402);
        }

        return back()->with('error', $this->getMessage());
    }
}
