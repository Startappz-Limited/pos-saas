<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Billing\RecordManualPayment;
use App\Enums\AuditEvent;
use App\Exceptions\PaymentRejected;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\RecordPlatformPaymentRequest;
use App\Models\Business;
use App\Support\Platform\PlatformAudit;
use Illuminate\Http\RedirectResponse;

/**
 * Manual payments and AI top-ups in the platform console (Phase 5, B2; until
 * M-Pesa go-live, decision 4.6). Recorded and applied together through the
 * Phase 4 action, attributed to the operator who entered them, audited.
 */
class PaymentController extends Controller
{
    public function store(RecordPlatformPaymentRequest $request, Business $business, RecordManualPayment $record): RedirectResponse
    {
        $data = $request->validated();

        try {
            $payment = $record->execute($business, $data['purpose'], (float) $data['amount'], $data['receipt'] ?? null, $request->user(), $data['notes'] ?? null);
        } catch (PaymentRejected $e) {
            return redirect()->route('platform.businesses.show', $business)->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        PlatformAudit::record('payment.recorded', $business, [
            'payment' => $payment->uuid,
            'purpose' => $payment->purpose,
            'amount' => (string) $payment->amount,
            'receipt' => $payment->mpesa_receipt,
        ], AuditEvent::CREATED);

        return redirect()->route('platform.businesses.show', $business)->with('success', __('The payment is recorded and applied.'));
    }
}
