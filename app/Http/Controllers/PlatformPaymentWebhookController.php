<?php

namespace App\Http\Controllers;

use App\Actions\Billing\ConfirmPlatformPayment;
use App\Models\PlatformPayment;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The M-Pesa callback for platform payments (Phase 4). It is only a nudge:
 * the body names a checkout request, and the payment is then settled by
 * querying the M-Pesa API (ConfirmPlatformPayment), never by trusting what
 * the callback says. Unknown references and repeats get the same quiet "ok",
 * so nothing is revealed and a retried callback is harmless.
 */
class PlatformPaymentWebhookController extends Controller
{
    public function __invoke(Request $request, ConfirmPlatformPayment $confirm, TenantContext $tenant): JsonResponse
    {
        $checkout = $request->input('checkout_request_id', $request->input('CheckoutRequestID'));

        if (is_string($checkout) && $checkout !== '') {
            // Platform-wide lookup on our own reference (never on a phone number):
            // the callback names no business.
            $payment = $tenant->bypass(fn () => PlatformPayment::query()->where('checkout_request_id', $checkout)->first());

            if ($payment !== null) {
                $confirm->execute($payment);
            }
        }

        return response()->json(['success' => true]);
    }
}
