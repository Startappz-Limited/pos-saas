<?php

namespace App\Actions;

use App\Actions\Baileys\NotifyCustomerOfCreditStatement;
use App\Models\CreditAccount;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Render a customer's outstanding-debt statement as a PDF.
 *
 * Deliberately NOT the full ledger: this is the document a shop sends to chase
 * payment, so it lists only what is still owed — every unpaid invoice with its
 * age — rather than every movement since the account opened.
 *
 * Backs both the web download and the WhatsApp document delivery
 * ({@see NotifyCustomerOfCreditStatement}), so what the customer receives and
 * what the shop sees are always the same document.
 */
class GenerateCreditStatementPdf
{
    /**
     * Render the statement and return the raw PDF bytes.
     */
    public function execute(CreditAccount $creditAccount): string
    {
        return Pdf::loadView('pdf.credit-statement', $this->data($creditAccount))->output();
    }

    /**
     * The statement's contents, also used to build the WhatsApp caption.
     *
     * @return array{creditAccount: CreditAccount, customer: mixed, shop: mixed, sales: Collection<int, Sale>, totalOwed: float, asAt: Carbon}
     */
    public function data(CreditAccount $creditAccount): array
    {
        $creditAccount->loadMissing(['customer', 'shop']);

        // Read outstanding debt from `sales`, not the ledger: an invoice number
        // and a date are what a customer can actually reconcile against, and it
        // keeps the statement correct even for a shop whose ledger predates the
        // credit rollout.
        $sales = Sale::query()
            ->where('customer_id', $creditAccount->customer_id)
            ->where('shop_id', $creditAccount->shop_id)
            ->where('payment_method', 'credit')
            ->where('status', '!=', 'voided')
            ->where('balance_due', '>', 0)
            ->orderBy('created_at')
            ->get();

        return [
            'creditAccount' => $creditAccount,
            'customer' => $creditAccount->customer,
            'shop' => $creditAccount->shop,
            'sales' => $sales,
            'totalOwed' => round((float) $sales->sum('balance_due'), 2),
            'asAt' => now(),
        ];
    }

    /**
     * Filename shown to the customer when they receive or download the statement.
     */
    public function filename(CreditAccount $creditAccount): string
    {
        $slug = Str::slug($creditAccount->customer?->code ?: $creditAccount->customer?->name ?: 'customer');

        return "statement-{$slug}-".now()->format('Y-m-d').'.pdf';
    }

    /**
     * Permanent signed link the customer can reopen the statement from.
     *
     * Mirrors the sale invoice: no expiry, but unguessable and tamper-proof, so
     * it is safe to hand to a customer over WhatsApp. It re-renders live, so a
     * customer who pays and reopens the link sees the reduced balance.
     */
    public function signedUrl(CreditAccount $creditAccount): string
    {
        return URL::signedRoute('credit-accounts.statement-pdf', $creditAccount);
    }

    /**
     * The WhatsApp caption sent alongside the PDF.
     *
     * Kept short: WhatsApp truncates long captions behind a "read more", and the
     * detail is in the attachment.
     */
    public function caption(CreditAccount $creditAccount): string
    {
        $data = $this->data($creditAccount);
        $shopName = $data['shop']?->name ?? 'Our Store';
        $count = $data['sales']->count();

        $lines = [];
        $lines[] = '📄 *Statement of Account*';
        $lines[] = "_{$shopName}_";
        $lines[] = '';
        $lines[] = 'As at: '.$data['asAt']->format('d M Y');
        $lines[] = "Unpaid invoices: {$count}";
        $lines[] = '*Total due: '.number_format($data['totalOwed'], 2).'*';
        $lines[] = '';
        $lines[] = $count > 0
            ? 'The attached statement lists each outstanding invoice. Please settle at your earliest convenience.'
            : 'Your account is fully settled. Thank you.';

        return implode("\n", $lines);
    }
}
