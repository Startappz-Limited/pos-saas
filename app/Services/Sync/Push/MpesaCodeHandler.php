<?php

namespace App\Services\Sync\Push;

use App\Services\Mpesa\TillPaymentRefused;
use App\Services\Mpesa\TillPayments;
use Illuminate\Support\Facades\Validator;

/**
 * An M-Pesa code a cashier typed in on an offline till (Phase 7, decision
 * 4.9): the customer paid the shop's Till or Paybill directly. The server
 * looks the code up with the shop's account and matches it by code and
 * amount; a match pays its sale once, anything else stays pending and goes on
 * the till sync review.
 */
final class MpesaCodeHandler implements PushHandler
{
    public function __construct(private TillPayments $payments) {}

    public function handle(PushItem $item): array
    {
        $data = Validator::make($item->data, [
            'mpesa_receipt' => ['required', 'string', 'alpha_num', 'between:8,20'],
            'amount' => ['required', 'numeric', 'min:1'],
            'sale_client_uuid' => ['nullable', 'uuid'],
            'phone' => ['nullable', 'string', 'max:20'],
        ])->validate();

        try {
            $request = $this->payments->recordCode($item->register, $item->user, $item->clientUuid, $data['mpesa_receipt'], (string) $data['amount'], $data['sale_client_uuid'] ?? null, $data['phone'] ?? null);
        } catch (TillPaymentRefused $e) {
            throw new PushRefused($e->errorCode, $e->getMessage());
        }

        return ['payment_request' => $request->present(), 'flags' => $request->status === 'pending' ? ['mpesa_pending'] : []];
    }
}
