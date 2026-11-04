<?php

namespace App\Support\Sales;

use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Exceptions\BusinessRuleException;
use App\Models\CashPayout;
use App\Models\CashRegister;
use App\Models\RefundDue;
use App\Models\Register;
use App\Models\Sale;
use App\Models\User;
use App\Support\Approvals\ApprovalInbox;
use App\Support\Approvals\ApprovalSubject;
use App\Support\Approvals\Approvers;
use App\Support\Approvals\Flags;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The money a voided sale took, handed back (stage 10, step 4; decided 4
 * November 2026). Approving a void and paying money out are separate:
 *
 * - cash the sale's own open session counted is taken out of it by
 *   recalculating its totals (the sale no longer counts);
 * - any other cash is paid out at once only from the voider's own open
 *   session in the shop; otherwise (an approved void request, a voider with no
 *   session of their own) it becomes a cash refund owed. Cash is never taken
 *   from someone else's session automatically;
 * - card, M-Pesa, bank transfer and cheque payments each become a refund owed,
 *   with their method and reference;
 * - "credit" is not money received and never creates a refund (the credit
 *   reversal clears what the customer still owed);
 * - money taken through an online store (a converted order that wasn't cash
 *   on delivery) is never a POS refund owed: the store refunds it (step 5b).
 *
 * A void made at a till (stage 10, step 5c; decided 4 November 2026) pays its
 * cash out of that till's session open at the void's time, whoever signed in to
 * void; a session already closed when an offline void syncs has the payout
 * recorded in it and its totals corrected, with an audit note, and the owners
 * are told; with no session open at that time the cash is owed.
 *
 * A refund owed is paid later by someone holding `sales.void` or
 * `refunds.approve` with access to the shop: cash out of their own open
 * session at that time; anything else done outside the system (an M-Pesa
 * reversal, a bank transfer) and recorded; a cheque may instead be settled as
 * returned.
 */
final class VoidRefunds
{
    public const PAYERS = ['sales.void', 'refunds.approve', 'sales.full-access'];

    public function __construct(private Approvers $approvers, private ApprovalInbox $inbox) {}

    /**
     * The money the sale took, part by part: what was paid with the sale
     * itself (its own method) and each payment recorded since.
     *
     * @return list<array{method: string, amount: float, reference: ?string, sale_payment_id: ?int, in_session_totals: bool}>
     */
    public function received(Sale $sale): array
    {
        $payments = $sale->payments()->orderBy('id')->get();
        $parts = [];

        $withSale = round((float) $sale->paid_amount - (float) $payments->sum('amount'), 2);
        // Paid in the online store: refunded there, never by the POS (decided 4 November 2026).
        $order = $sale->ecommerceOrder;
        if ($withSale > 0 && ! ($order !== null && ! $order->is_cod)) {
            $parts[] = [
                'method' => (string) $sale->payment_method,
                'amount' => $withSale,
                'reference' => null,
                'sale_payment_id' => null,
                // Counted in its session's expected cash (CashRegister::updateSalesTotals).
                'in_session_totals' => $sale->status === 'completed' && $sale->payment_method === 'cash',
            ];
        }

        foreach ($payments as $payment) {
            $parts[] = [
                'method' => (string) $payment->payment_method,
                'amount' => round((float) $payment->amount, 2),
                'reference' => $payment->reference,
                'sale_payment_id' => (int) $payment->id,
                'in_session_totals' => false,
            ];
        }

        return array_values(array_filter($parts, fn (array $part): bool => $part['amount'] > 0 && in_array($part['method'], RefundDue::METHODS, true)));
    }

    /**
     * Hands back what the voided sale took. Call inside the void's transaction,
     * with the sale as it was before the void (its status and session).
     *
     * @param  bool  $approvedRequest  an approved void request: cash is never paid out at once
     * @return array{payouts: list<CashPayout>, refunds: list<RefundDue>}
     */
    public function settle(Sale $sale, ?User $by, bool $approvedRequest = false, ?Register $till = null, ?CarbonInterface $at = null): array
    {
        $sessionOpen = $sale->register_id !== null && $sale->register?->status === 'open';
        // At a till, its session at the void's time pays; otherwise the voider's own
        // open session (no person, as an automatic void, has none: cash is owed).
        $pays = match (true) {
            $till !== null => $this->tillSessionAt($till, $at ?? now()),
            $approvedRequest || $by === null => null,
            default => $this->ownSession($by, (int) $sale->shop_id),
        };
        $payouts = [];
        $refunds = [];
        $corrected = false;

        foreach ($this->received($sale) as $part) {
            if ($part['method'] === 'cash') {
                if ($part['in_session_totals'] && $sessionOpen) {
                    continue; // leaves the sale's own session when its totals are recalculated
                }
                if ($pays !== null) {
                    $payouts[] = CashPayout::create([
                        'shop_id' => $sale->shop_id, 'cash_register_id' => $pays->id, 'amount' => $part['amount'],
                        'reason' => __('Cash paid out by :name (void of :invoice)', ['name' => $by?->name ?? __('the system'), 'invoice' => $sale->invoice_number]),
                        'sale_id' => $sale->id, 'created_by' => $by?->id,
                    ]);
                    $corrected = $corrected || $pays->status !== 'open';

                    continue;
                }
            }

            $refunds[] = RefundDue::create([
                'shop_id' => $sale->shop_id, 'sale_id' => $sale->id, 'sale_payment_id' => $part['sale_payment_id'],
                'source' => RefundDue::SOURCE_VOID, 'method' => $part['method'], 'amount' => $part['amount'],
                'reference' => $part['reference'], 'status' => 'pending', 'created_by' => $by?->id,
            ]);
        }

        foreach ($refunds as $refund) {
            $this->inbox->request($this->subject($refund), $this->payersOf($refund, $by));
        }

        if ($corrected) {
            $this->correctClosedSession($pays, $sale, $by);
        }

        return ['payouts' => $payouts, 'refunds' => $refunds];
    }

    /**
     * The till's session open at $at, open or closed since.
     */
    public function tillSessionAt(Register $till, CarbonInterface $at): ?CashRegister
    {
        return CashRegister::query()->where('register_id', $till->id)->where('opened_at', '<=', $at)
            ->where(fn ($query) => $query->whereNull('closed_at')->orWhere('closed_at', '>=', $at))
            ->latest('opened_at')->first();
    }

    /**
     * An offline void that syncs after its session closed (decided 4 November
     * 2026): the payout stays in that session, whose expected balance and
     * variance are recalculated, with an audit note; the owners are told.
     */
    private function correctClosedSession(CashRegister $session, Sale $sale, ?User $by): void
    {
        $before = ['expected_balance' => (float) $session->expected_balance, 'variance' => (float) $session->variance];
        $expected = $session->calculateExpectedBalance();
        $session->forceFill([
            'expected_balance' => $expected,
            'variance' => $session->closing_balance === null ? $session->variance : round((float) $session->closing_balance - $expected, 2),
        ])->save();

        AuditableEvent::dispatch(
            auditableType: CashRegister::class, auditableId: $session->id, auditableUuid: (string) $session->uuid, event: AuditEvent::UPDATED,
            oldValues: $before, newValues: ['expected_balance' => (float) $session->expected_balance, 'variance' => (float) $session->variance,
                'note' => __('Corrected after closing: an offline void of :invoice synced late and its cash was paid out of this session.', ['invoice' => $sale->invoice_number])],
            userId: $by?->id, userName: $by?->name, shopId: (int) $session->shop_id, tags: ['cash-register', 'corrected', 'void'], businessId: (int) $sale->business_id,
        );

        app(Flags::class)->raise($session, (int) $session->shop_id, (int) $sale->business_id, 'session_corrected_by_void:'.$sale->uuid,
            __('Session :number corrected after closing', ['number' => $session->register_number]),
            __('An offline void of sale :invoice synced after this session closed. Its cash was paid out of this session, so its expected balance and variance were recalculated.', ['invoice' => $sale->invoice_number]),
            route('cash-registers.show', $session), notifyOwners: true);
    }

    /**
     * Records a refund owed as paid (or, for a cheque, settled as returned).
     * Cash comes out of the payer's own open session in the shop, then.
     *
     * @param  array{resolution: string, method?: ?string, reference?: ?string, notes?: ?string}  $data
     */
    public function pay(RefundDue $refund, User $by, array $data): RefundDue
    {
        return DB::transaction(function () use ($refund, $by, $data): RefundDue {
            $refund = RefundDue::query()->whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if (! $refund->isPending()) {
                throw new BusinessRuleException(__('This refund has already been settled.'));
            }

            $resolution = $data['resolution'];
            if ($resolution === 'cheque_returned') {
                if ($refund->method !== 'cheque') {
                    throw new BusinessRuleException(__('Only a cheque refund can be settled as returned.'));
                }
                $refund->update(['status' => 'settled', 'resolution' => 'cheque_returned', 'resolved_by' => $by->id, 'resolved_at' => now(), 'notes' => $data['notes'] ?? null]);
            } else {
                $method = $data['method'] ?? $refund->method;
                $payoutId = null;
                if ($method === 'cash') {
                    $own = $this->ownSession($by, (int) $refund->shop_id)
                        ?? throw new BusinessRuleException(__('Open your register to pay a cash refund.'));
                    $payoutId = CashPayout::create([
                        'shop_id' => $refund->shop_id, 'cash_register_id' => $own->id, 'amount' => $refund->amount,
                        'reason' => __('Cash paid out by :name (refund owed for :invoice)', ['name' => $by->name, 'invoice' => $refund->sale?->invoice_number]), 'sale_id' => $refund->sale_id, 'created_by' => $by->id,
                    ])->id;
                } elseif (trim((string) ($data['reference'] ?? '')) === '') {
                    throw new BusinessRuleException(__('Enter the reference of the refund (the M-Pesa, bank or card reference).'));
                }

                $refund->update([
                    'status' => 'paid', 'resolution' => 'paid', 'resolved_method' => $method, 'resolved_reference' => $data['reference'] ?? null,
                    'cash_payout_id' => $payoutId, 'resolved_by' => $by->id, 'resolved_at' => now(), 'notes' => $data['notes'] ?? null,
                ]);
            }

            $this->inbox->resolve('refund_due', (string) $refund->uuid, $refund->resolution);

            return $refund->fresh();
        });
    }

    /**
     * The person's own open session in the shop, if they have one.
     */
    public function ownSession(User $user, int $shopId): ?CashRegister
    {
        return CashRegister::query()->where('shop_id', $shopId)->where('user_id', $user->id)->where('status', 'open')
            ->latest('opened_at')->first();
    }

    /**
     * @return Collection<int, User>
     */
    public function payersOf(RefundDue $refund, ?User $except = null): Collection
    {
        return $this->approvers->eligible(self::PAYERS, (int) $refund->shop_id, $except);
    }

    public function subject(RefundDue $refund): ApprovalSubject
    {
        $refund->loadMissing(['sale', 'shop']);

        return new ApprovalSubject(
            kind: 'refund_due',
            uuid: (string) $refund->uuid,
            businessId: (int) $refund->shop?->business_id,
            title: __('Refund owed: :amount (:method) for sale :invoice', [
                'amount' => number_format((float) $refund->amount, 2), 'method' => $refund->methodLabel(), 'invoice' => $refund->sale?->invoice_number,
            ]),
            summary: __('The sale at :shop was voided. Pay the customer back and record it.', ['shop' => $refund->shop?->name]),
            url: route('refunds-due.index'),
            requestedBy: (string) $refund->creator?->name,
            prompt: __('Refund to pay'),
        );
    }
}
