<?php

namespace App\Services\Mpesa;

use App\Contracts\MpesaGateway;

/**
 * For tests: passes unless told to fail, and remembers what it was asked. An
 * STK Push stays pending until the test settles it; a receipt is found only
 * once the test records it.
 */
class FakeMpesaGateway implements MpesaGateway
{
    /** @var list<array{shortcode: string, type: string, credentials: array<string, mixed>}> */
    public array $checks = [];

    /** @var list<array{shortcode: string, phone: string, amount: string, reference: string}> */
    public array $pushes = [];

    /** @var array<string, MpesaPayment> by checkout request id */
    private array $statuses = [];

    /** @var array<string, array{shortcode: string, payment: MpesaPayment}> by receipt */
    private array $transactions = [];

    private ?string $stkRefusal = null;

    public function __construct(private ?string $failure = null) {}

    public function failWith(string $error): self
    {
        $this->failure = $error;

        return $this;
    }

    public function refuseStk(string $error): self
    {
        $this->stkRefusal = $error;

        return $this;
    }

    /**
     * Settles an STK Push as the API would report it.
     */
    public function settle(string $checkoutRequestId, MpesaPayment $payment): self
    {
        $this->statuses[$checkoutRequestId] = $payment;

        return $this;
    }

    /**
     * A payment the API knows, made to $shortcode.
     */
    public function recordTransaction(string $shortcode, MpesaPayment $payment): self
    {
        $this->transactions[(string) $payment->receipt] = ['shortcode' => $shortcode, 'payment' => $payment];

        return $this;
    }

    public function verify(string $shortcode, string $type, array $credentials): MpesaCheck
    {
        $this->checks[] = compact('shortcode', 'type', 'credentials');

        return $this->failure === null ? MpesaCheck::passed() : MpesaCheck::failed($this->failure);
    }

    public function stkPush(MpesaAccount $account, string $phone, string $amount, string $reference, string $description): StkRequest
    {
        if ($this->stkRefusal !== null) {
            return StkRequest::refused($this->stkRefusal);
        }

        $this->pushes[] = ['shortcode' => $account->shortcode, 'phone' => $phone, 'amount' => $amount, 'reference' => $reference];

        return StkRequest::sent('ws_CO_'.count($this->pushes).'_'.$reference, 'MR_'.count($this->pushes));
    }

    public function stkStatus(MpesaAccount $account, string $checkoutRequestId): MpesaPayment
    {
        return $this->statuses[$checkoutRequestId] ?? MpesaPayment::pending();
    }

    public function findTransaction(MpesaAccount $account, string $receipt): ?MpesaPayment
    {
        $known = $this->transactions[$receipt] ?? null;

        // Only a payment to this shop's own number counts.
        return $known !== null && $known['shortcode'] === $account->shortcode ? $known['payment'] : null;
    }
}
