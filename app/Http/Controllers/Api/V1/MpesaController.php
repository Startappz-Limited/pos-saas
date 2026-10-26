<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiV1;
use App\Models\PaymentRequest;
use App\Models\Register;
use App\Services\Mpesa\TillPaymentRefused;
use App\Services\Mpesa\TillPayments;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M-Pesa at a paired till (Phase 7): ask for an STK Push to the customer's
 * phone, then ask what became of it. The server confirms only by querying the
 * M-Pesa API; a till that goes offline meanwhile gets the result in its next
 * pull (`payment_requests`).
 */
class MpesaController extends Controller
{
    public function __construct(private TenantContext $tenant, private TillPayments $payments) {}

    /**
     * Sends an STK Push from the till's shop. The same `client_uuid` again
     * returns the same request, so a retried call never charges twice.
     */
    public function stk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_uuid' => ['required', 'uuid'],
            'sale_client_uuid' => ['nullable', 'uuid'],
            'phone' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'min:1', 'max:250000'],
        ]);

        $register = Register::query()->findOrFail($this->tenant->registerId());

        try {
            $paymentRequest = $this->payments->requestStk($register, $request->user(), $validated['client_uuid'], $validated['phone'], (string) $validated['amount'], $validated['sale_client_uuid'] ?? null);
        } catch (TillPaymentRefused $e) {
            return ApiV1::error($e->errorCode, $e->getMessage(), 422);
        }

        return ApiV1::ok($paymentRequest->present(), $paymentRequest->status === PaymentRequest::FAILED
            ? __('M-Pesa did not send the request: :reason', ['reason' => $paymentRequest->failure_reason])
            : __('Ask the customer to enter their M-Pesa PIN.'));
    }

    /**
     * What became of a request: asks M-Pesa again while it is pending.
     */
    public function show(PaymentRequest $paymentRequest): JsonResponse
    {
        return ApiV1::ok($this->payments->check($paymentRequest)->present());
    }
}
