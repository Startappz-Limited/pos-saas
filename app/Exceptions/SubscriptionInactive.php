<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A write refused with the code `subscription_inactive` because the business
 * is read-only (Phase 4): its
 * subscription lapsed, or it has more shops than it pays for. Reading and
 * exporting still work.
 */
class SubscriptionInactive extends RuntimeException
{
    public const LAPSED = 'subscription_inactive';

    public const OVER_QUOTA = 'shops_over_quota';

    public function __construct(public readonly string $reason = self::LAPSED)
    {
        parent::__construct($reason === self::OVER_QUOTA
            ? __('This business has more shops than its plan covers. Delete shops or upgrade to make changes again.')
            : __('Your subscription has lapsed, so this business is read-only. Renew to make changes. You can still view and export your data.'));
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'code' => self::LAPSED,
                'reason' => $this->reason,
                'message' => $this->getMessage(),
            ], 402);
        }

        return back()->with('error', $this->getMessage());
    }
}
