<?php

namespace App\Actions\Baileys;

use App\Actions\GenerateCreditStatementPdf;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Support\BaileysJid;
use Illuminate\Support\Facades\Log;

/**
 * Send a customer their statement of outstanding debt over Baileys (Chatway Gateway).
 *
 * Deliberately on the Baileys path rather than the Meta Cloud API: chasing a
 * debt means messaging a customer who has almost certainly not messaged the shop
 * in the last 24 hours, which the Cloud API refuses (error 131047). Baileys has
 * no such window.
 *
 * Unlike the sale notifications this is NOT gated on the per-shop sale-message
 * switches — it is a deliberate, human-initiated action, not an automatic
 * broadcast, so a shop that has muted sale receipts can still chase payment.
 *
 * If the PDF cannot be rendered the message degrades to the plain-text summary
 * rather than failing outright.
 */
class NotifyCustomerOfCreditStatement
{
    public function __construct(
        protected SendBaileysMessage $sendMessage,
        protected SendBaileysMediaMessage $sendMedia,
        protected GenerateCreditStatementPdf $statementPdf,
    ) {}

    public function execute(CreditAccount $creditAccount, ?int $sentBy = null): ?BaileysMessage
    {
        $creditAccount->loadMissing(['customer', 'shop']);

        /** @var Customer|null $customer */
        $customer = $creditAccount->customer;

        if (! $customer || empty($customer->phone)) {
            return null;
        }

        $jid = BaileysJid::fromPhone($customer->phone);

        if (! $jid) {
            return null;
        }

        $session = BaileysSession::query()
            ->where('shop_id', $creditAccount->shop_id)
            ->connected()
            ->latest('connected_at')
            ->first();

        if (! $session) {
            return null;
        }

        $caption = $this->statementPdf->caption($creditAccount);

        return $this->sendStatement($creditAccount, $session, $jid, $customer, $caption, $sentBy)
            ?? $this->sendMessage->execute(
                session: $session,
                toJid: $jid,
                text: $caption,
                sentBy: $sentBy,
                customerId: $customer->id,
            );
    }

    /**
     * Attempt to deliver the statement PDF as a WhatsApp document.
     *
     * Returns null when the PDF could not be produced, letting the caller fall
     * back to the plain-text summary — a customer being told what they owe in
     * text is far better than being told nothing.
     */
    protected function sendStatement(
        CreditAccount $creditAccount,
        BaileysSession $session,
        string $jid,
        Customer $customer,
        string $caption,
        ?int $sentBy,
    ): ?BaileysMessage {
        try {
            $bytes = $this->statementPdf->execute($creditAccount);
        } catch (\Throwable $e) {
            Log::warning('Credit statement PDF render failed, falling back to text', [
                'credit_account_id' => $creditAccount->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        // Bytes go inline as base64 so the gateway never has to reach back
        // into Laravel — same rationale as the sale invoice.
        return $this->sendMedia->execute(
            session: $session,
            toJid: $jid,
            mediaType: 'document',
            url: $this->statementPdf->signedUrl($creditAccount),
            caption: $caption,
            extras: [
                'mimetype' => 'application/pdf',
                'filename' => $this->statementPdf->filename($creditAccount),
                'data' => base64_encode($bytes),
            ],
            sentBy: $sentBy,
            customerId: $customer->id,
        );
    }
}
