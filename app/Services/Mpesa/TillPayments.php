<?php

namespace App\Services\Mpesa;

use App\Actions\Sale\RecordSalePayment;
use App\Contracts\MpesaGateway;
use App\Models\PaymentRequest;
use App\Models\Register;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\SyncIssue;
use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\Sync\SyncIssues;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * M-Pesa at the till (Phase 7, decision 4.9; open question 2): STK Pushes
 * through the shop's own Till or Paybill, and M-Pesa codes a cashier entered
 * on an offline till.
 *
 * A payment counts only when the M-Pesa API, queried with the shop's account,
 * says it was made (never because a till or a callback claimed it). Once
 * confirmed, it is recorded against its sale exactly once: at confirmation if
 * the sale is already on the server, or when the till pushes the sale.
 * Anything that doesn't add up (a code M-Pesa doesn't know, a different
 * amount, a code already used, a payment for a sale already paid) stays
 * unapplied and goes on the till sync review.
 */
class TillPayments
{
    /** An STK Push not settled after this long is given up. */
    public const STK_EXPIRY_MINUTES = 30;

    /** A code M-Pesa doesn't know is looked up again for this long, then left unmatched. */
    public const CODE_RETRY_HOURS = 48;

    public function __construct(
        private MpesaGateway $gateway,
        private RecordSalePayment $recordPayment,
        private SyncIssues $issues,
    ) {}

    /**
     * Sends an STK Push from the till's shop to $phone. Asking again with the
     * same client uuid returns the same request.
     *
     * @throws TillPaymentRefused
     */
    public function requestStk(Register $register, User $user, string $clientUuid, string $phone, string $amount, ?string $saleClientUuid): PaymentRequest
    {
        $existing = PaymentRequest::query()->where('client_uuid', $clientUuid)->first();
        if ($existing !== null) {
            return $existing;
        }

        $account = MpesaAccount::forShop($register->shop) ?? throw TillPaymentRefused::notSetUp();
        $msisdn = PhoneNumber::normalize($phone) ?? throw TillPaymentRefused::badPhone();

        $request = PaymentRequest::create([
            'client_uuid' => $clientUuid,
            'shop_id' => $register->shop_id,
            'register_id' => $register->id,
            'requested_by' => $user->id,
            'method' => PaymentRequest::METHOD_STK,
            'status' => PaymentRequest::PENDING,
            'sale_client_uuid' => $saleClientUuid,
            'phone' => $msisdn,
            'amount' => $amount,
            'shortcode' => $account->shortcode,
        ]);

        $sent = $this->gateway->stkPush($account, $msisdn, $request->amount, $request->uuid, (string) $register->shop?->name);

        $request->update($sent->accepted
            ? ['checkout_request_id' => $sent->checkoutRequestId, 'merchant_request_id' => $sent->merchantRequestId]
            : ['status' => PaymentRequest::FAILED, 'failure_reason' => $sent->error]);

        return $request;
    }

    /**
     * Records an M-Pesa code a cashier entered on an offline till (pushed with
     * the till's outbox), and looks it up at once.
     *
     * @throws TillPaymentRefused
     */
    public function recordCode(Register $register, User $user, string $clientUuid, string $receipt, string $amount, ?string $saleClientUuid, ?string $phone = null): PaymentRequest
    {
        $receipt = strtoupper(trim($receipt));
        $account = MpesaAccount::forShop($register->shop) ?? throw TillPaymentRefused::notSetUp();

        $attributes = [
            'client_uuid' => $clientUuid,
            'shop_id' => $register->shop_id,
            'register_id' => $register->id,
            'requested_by' => $user->id,
            'method' => PaymentRequest::METHOD_CODE,
            'sale_client_uuid' => $saleClientUuid,
            'phone' => PhoneNumber::normalize($phone),
            'amount' => $amount,
            'shortcode' => $account->shortcode,
        ];

        // A code already used for another payment can't pay twice.
        // Receipts are unique on the whole platform (M-Pesa codes are), so this looks past the business.
        if (DB::table('payment_requests')->where('mpesa_receipt', $receipt)->exists()) {
            $request = PaymentRequest::create([...$attributes, 'status' => PaymentRequest::UNMATCHED, 'failure_reason' => __('This M-Pesa code was already used for another payment.')]);
            $this->issue(SyncIssue::MPESA_CODE_REUSED, $receipt, $request, ['mpesa_receipt' => $receipt]);

            return $request;
        }

        $request = PaymentRequest::create([...$attributes, 'status' => PaymentRequest::PENDING, 'mpesa_receipt' => $receipt]);
        $this->check($request);

        return $request->fresh();
    }

    /**
     * Asks M-Pesa what became of a pending request, and acts on the answer.
     */
    public function check(PaymentRequest $request): PaymentRequest
    {
        if (! $request->isPending()) {
            return $request;
        }

        $account = MpesaAccount::forShop($request->register?->shop ?? Shop::query()->findOrFail($request->shop_id));
        if ($account === null) {
            return $request;
        }

        $answer = $request->method === PaymentRequest::METHOD_STK
            ? ($request->checkout_request_id ? $this->gateway->stkStatus($account, $request->checkout_request_id) : MpesaPayment::failed(__('No STK Push was sent.')))
            : $this->gateway->findTransaction($account, (string) $request->mpesa_receipt);

        $request->forceFill(['checks' => $request->checks + 1, 'last_checked_at' => now()])->save();

        if ($answer === null || $answer->state === MpesaPayment::PENDING) {
            $this->giveUpIfStale($request);

            return $request;
        }

        if ($answer->state === MpesaPayment::FAILED) {
            $request->update(['status' => PaymentRequest::FAILED, 'failure_reason' => $answer->error]);

            return $request;
        }

        // A code is matched by its amount too (decision 4.9).
        if ($request->method === PaymentRequest::METHOD_CODE && round((float) $answer->amount, 2) !== round((float) $request->amount, 2)) {
            $request->update(['status' => PaymentRequest::UNMATCHED, 'paid_amount' => $answer->amount, 'failure_reason' => __('M-Pesa shows a different amount for this code.')]);
            $this->issue(SyncIssue::MPESA_AMOUNT_MISMATCH, $request->uuid, $request, ['mpesa_receipt' => $request->mpesa_receipt, 'paid_amount' => $answer->amount]);

            return $request;
        }

        try {
            $request->update([
                'status' => PaymentRequest::CONFIRMED,
                'mpesa_receipt' => $answer->receipt ?? $request->mpesa_receipt,
                'paid_amount' => $answer->amount,
                'confirmed_at' => $answer->paidAt ?? now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same M-Pesa payment was already entered as a code (or the other way round).
            $request->refresh()->update(['status' => PaymentRequest::UNMATCHED, 'failure_reason' => __('This M-Pesa payment was already recorded for another payment request.')]);
            $this->issue(SyncIssue::MPESA_CODE_REUSED, (string) $answer->receipt, $request, ['mpesa_receipt' => $answer->receipt]);

            return $request;
        }

        $this->apply($request);

        return $request->fresh();
    }

    /**
     * Records every confirmed, unapplied payment for a sale the till has just
     * pushed.
     */
    public function applyForSale(Sale $sale): void
    {
        if ($sale->client_uuid === null) {
            return;
        }

        PaymentRequest::query()->where('sale_client_uuid', $sale->client_uuid)
            ->where('status', PaymentRequest::CONFIRMED)->whereNull('applied_at')->get()
            ->each(fn (PaymentRequest $request) => $this->apply($request));
    }

    /**
     * Records a confirmed payment against its sale, once: the request is
     * claimed first, so two attempts at once can't both record it.
     */
    public function apply(PaymentRequest $request): void
    {
        $sale = $request->sale_client_uuid === null ? null : Sale::query()->where('client_uuid', $request->sale_client_uuid)->first();

        if ($request->status !== PaymentRequest::CONFIRMED || $request->applied_at !== null || $sale === null) {
            return;
        }

        if ($sale->payment_status === 'paid') {
            $this->issue(SyncIssue::MPESA_NOT_NEEDED, $request->uuid, $request, ['mpesa_receipt' => $request->mpesa_receipt, 'sale' => $sale->uuid]);

            return;
        }

        $claimed = PaymentRequest::query()->whereKey($request->id)->where('status', PaymentRequest::CONFIRMED)->whereNull('applied_at')
            ->update(['applied_at' => now(), 'sale_id' => $sale->id]);
        if ($claimed !== 1) {
            return;
        }

        try {
            $sale = $this->recordPayment->execute($sale, [[
                'amount' => $request->paid_amount ?? $request->amount,
                'payment_method' => 'mobile_money',
                'reference' => $request->mpesa_receipt,
                'notes' => __('M-Pesa at the till'),
            ]], $request->confirmed_at ?? now(), $request->client_uuid, $request->requested_by);

            $request->forceFill(['sale_payment_id' => $sale->payments()->latest('id')->value('id')])->saveQuietly();
        } catch (Throwable $e) {
            PaymentRequest::query()->whereKey($request->id)->update(['applied_at' => null]);

            throw $e;
        }
    }

    /**
     * An STK Push never settled expires; a code M-Pesa still doesn't know
     * after a while is left unmatched for review.
     */
    private function giveUpIfStale(PaymentRequest $request): void
    {
        $age = Carbon::parse($request->created_at);

        if ($request->method === PaymentRequest::METHOD_STK && $age->lessThan(now()->subMinutes(self::STK_EXPIRY_MINUTES))) {
            $request->update(['status' => PaymentRequest::FAILED, 'failure_reason' => __('The customer did not complete the payment in time.')]);
        }

        if ($request->method === PaymentRequest::METHOD_CODE && $age->lessThan(now()->subHours(self::CODE_RETRY_HOURS))) {
            $request->update(['status' => PaymentRequest::UNMATCHED, 'failure_reason' => __('M-Pesa has no payment with this code to this shop.')]);
        }

        if ($request->method === PaymentRequest::METHOD_CODE) {
            $this->issue(SyncIssue::MPESA_UNMATCHED, $request->uuid, $request, ['mpesa_receipt' => $request->mpesa_receipt, 'status' => $request->status]);
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function issue(string $type, string $key, PaymentRequest $request, array $details): void
    {
        $this->issues->record($type, $key, [
            ...$details,
            'payment_request' => $request->uuid,
            'amount' => number_format((float) $request->amount, 2, '.', ''),
            'sale_client_uuid' => $request->sale_client_uuid,
        ], $request->register);
    }
}
