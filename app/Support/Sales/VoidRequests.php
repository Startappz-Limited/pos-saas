<?php

namespace App\Support\Sales;

use App\Actions\Sale\VoidSale;
use App\Exceptions\BusinessRuleException;
use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\User;
use App\Support\Approvals\ApprovalInbox;
use App\Support\Approvals\ApprovalSubject;
use App\Support\Approvals\Approvers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Void requests under the shared approval rule (decided 3 November 2026,
 * stage 10, step 2). Someone without `sales.void` asks, with a reason, while
 * the sale can still be voided; everyone who may approve (holding `sales.void`
 * in the business, with access to the shop, other than the requester) is
 * notified; the first approval voids the sale, the first rejection (with a
 * reason) closes it. The window is checked at the request's time, so an
 * approval after the window still voids a sale asked about in time, up to
 * SaleVoidRequest::EXPIRES_AFTER_HOURS after the request.
 */
final class VoidRequests
{
    public const PERMISSIONS = ['sales.void', 'sales.full-access'];

    public function __construct(
        private VoidWindow $window,
        private Approvers $approvers,
        private ApprovalInbox $inbox,
        private VoidSale $voidSale,
    ) {}

    /**
     * @throws BusinessRuleException a refusal the person can act on (422)
     */
    public function request(Sale $sale, User $requester, string $reason): SaleVoidRequest
    {
        return DB::transaction(function () use ($sale, $requester, $reason): SaleVoidRequest {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status === 'voided') {
                throw new BusinessRuleException(__('This sale is already voided.'));
            }
            if ($this->approvers->holds($requester, self::PERMISSIONS)) {
                throw new BusinessRuleException(__('You can void this sale yourself.'));
            }
            if (SaleVoidRequest::query()->pending()->where('sale_id', $sale->id)->exists()) {
                throw new BusinessRuleException(__('A void of this sale has already been requested.'));
            }
            if (! $this->window->allows($sale, now())) {
                throw new BusinessRuleException($this->window->refusal($sale, now()));
            }

            $request = SaleVoidRequest::create([
                'shop_id' => $sale->shop_id,
                'sale_id' => $sale->id,
                'requested_by' => $requester->id,
                'reason' => $reason,
                'status' => 'pending',
                'requested_at' => now(),
                'expires_at' => now()->addHours(SaleVoidRequest::EXPIRES_AFTER_HOURS),
            ]);

            $this->inbox->request($this->subject($request), $this->approversOf($request));

            return $request;
        });
    }

    /**
     * Voids the sale. Refused when the request expired, or the sale couldn't
     * be voided any more when it was asked about.
     */
    public function approve(SaleVoidRequest $request, User $approver, ?string $note = null): SaleVoidRequest
    {
        return DB::transaction(function () use ($request, $approver, $note): SaleVoidRequest {
            $request = SaleVoidRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($request);

            $sale = Sale::query()->whereKey($request->sale_id)->lockForUpdate()->firstOrFail();
            if ($sale->status === 'voided') {
                throw new BusinessRuleException(__('This sale is already voided.'));
            }
            if (! $this->window->allows($sale, $request->requested_at)) {
                throw new BusinessRuleException($this->window->refusal($sale, $request->requested_at));
            }

            $this->voidSale->execute($sale, $request->reason, $approver);
            $request->update(['status' => 'approved', 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_note' => $note]);
            $this->inbox->settle($this->subject($request), $request->requester, 'approved', $approver, $note);

            return $request->fresh();
        });
    }

    public function reject(SaleVoidRequest $request, User $approver, string $reason): SaleVoidRequest
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Rejecting a void request needs a reason.'));
        }

        return DB::transaction(function () use ($request, $approver, $reason): SaleVoidRequest {
            $request = SaleVoidRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($request);

            $request->update(['status' => 'rejected', 'decided_by' => $approver->id, 'decided_at' => now(), 'decision_note' => $reason]);
            $this->inbox->settle($this->subject($request), $request->requester, 'rejected', $approver, $reason);

            return $request->fresh();
        });
    }

    public function cancel(SaleVoidRequest $request, User $requester): SaleVoidRequest
    {
        return DB::transaction(function () use ($request, $requester): SaleVoidRequest {
            $request = SaleVoidRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();
            if (! $request->isPending()) {
                throw new BusinessRuleException(__('Only a pending request can be cancelled.'));
            }

            $request->update(['status' => 'cancelled', 'decided_by' => $requester->id, 'decided_at' => now()]);
            $this->inbox->resolve('void_request', (string) $request->uuid, 'cancelled');

            return $request->fresh();
        });
    }

    /**
     * Marks a request past its expiry expired; its approvers' notifications
     * are resolved and its requester told.
     */
    public function expire(SaleVoidRequest $request): void
    {
        $request->update(['status' => 'expired', 'decided_at' => now()]);
        $this->inbox->resolve('void_request', (string) $request->uuid, 'expired');
        if ($request->requester !== null) {
            $this->inbox->settle($this->subject($request), $request->requester, 'expired', $request->requester);
        }
    }

    /**
     * @return Collection<int, User>
     */
    public function approversOf(SaleVoidRequest $request): Collection
    {
        return $this->approvers->eligible(self::PERMISSIONS, (int) $request->shop_id, $request->requester);
    }

    public function subject(SaleVoidRequest $request): ApprovalSubject
    {
        $request->loadMissing(['sale', 'shop', 'requester']);

        return new ApprovalSubject(
            kind: 'void_request',
            uuid: (string) $request->uuid,
            businessId: (int) $request->shop?->business_id,
            title: __('Void sale :invoice', ['invoice' => $request->sale?->invoice_number]),
            summary: __(':amount at :shop. Reason: :reason', [
                'amount' => number_format((float) $request->sale?->total_amount, 2),
                'shop' => $request->shop?->name,
                'reason' => $request->reason,
            ]),
            url: route('sales.show', $request->sale),
            requestedBy: (string) $request->requester?->name,
        );
    }

    private function assertOpen(SaleVoidRequest $request): void
    {
        // An expired request is marked so by the hourly sweep (approvals:remind).
        if ($request->hasExpired()) {
            throw new BusinessRuleException(__('This void request expired: it wasn\'t approved within :hours hours. Ask again, or use a return.', ['hours' => SaleVoidRequest::EXPIRES_AFTER_HOURS]));
        }
        if (! $request->isPending()) {
            throw new BusinessRuleException(__('This void request has already been settled.'));
        }
    }
}
