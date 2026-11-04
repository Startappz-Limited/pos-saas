<?php

namespace App\Services\Sync\Push;

use App\Actions\Sale\VoidSale;
use App\Enums\ApiErrorCode;
use App\Models\Sale;
use App\Models\SyncIssue;
use App\Services\Sync\TradingLease;
use App\Support\Sales\VoidWindow;
use Illuminate\Support\Facades\Validator;

/**
 * A void a till recorded, usually offline (stage 10, step 5c). The person
 * (`voided_by`) must hold `sales.void` here, checked again on the server; the
 * time limit is judged at the item's `recorded_at`, which must lie inside a
 * lease the till was given. Applied once per client uuid (SyncPush's
 * receipts), and released and audited as an online void, recording the till
 * and the time it recorded it. The cash comes out of the till's session open
 * at that time (decided 4 November 2026).
 *
 * - sale not on the server yet (later in the push, or not pushed): `pending`;
 * - already voided elsewhere: `applied`, `already_voided`, so the till clears it;
 * - a return since (one that isn't rejected): refused `sale_has_returns`, and a
 *   `void_refused` issue on the till sync review.
 */
final class SaleVoidHandler implements PushHandler
{
    public function __construct(
        private VoidSale $void,
        private VoidWindow $window,
        private TradingLease $leases,
        private PushPeople $people,
    ) {}

    public function handle(PushItem $item): array
    {
        $data = Validator::make($item->data, [
            'sale' => ['required_without:sale_client_uuid', 'nullable', 'uuid'],
            'sale_client_uuid' => ['required_without:sale', 'nullable', 'uuid'],
            'reason' => ['required', 'string', 'max:255'],
            'voided_by' => ['nullable', 'uuid'],
        ])->validate();

        if (! $this->leases->covers($item->register->id, $item->recordedAt)) {
            throw new PushRefused(ApiErrorCode::OutsideTradingLease, __('This void was recorded outside the time this till was allowed to trade offline.'));
        }

        $sale = Sale::query()
            ->when($data['sale'] ?? null, fn ($query, $uuid) => $query->where('uuid', $uuid))
            ->when(! ($data['sale'] ?? null), fn ($query) => $query->where('client_uuid', $data['sale_client_uuid']))
            ->where('shop_id', $item->register->shop_id)
            ->first() ?? throw new PushPending(ApiErrorCode::SaleNotSynced, __('The sale hasn\'t reached the server yet. The void is sent again after it.'));

        if ($sale->status === 'voided') {
            return ['sale_uuid' => $sale->uuid, 'already_voided' => true, 'flags' => []];
        }

        $person = $this->people->staff($data['voided_by'] ?? null, $item, 'voided_by');
        if (! ($person->can('sales.void') || $person->can('sales.full-access'))) {
            throw new PushRefused(ApiErrorCode::Forbidden, __('That person can\'t void sales.'), ['voided_by' => [__('That person can\'t void sales.')]]);
        }

        if (VoidWindow::hasOpenReturns($sale)) {
            throw new PushRefused(ApiErrorCode::SaleHasReturns, __(VoidWindow::RETURNS_REFUSAL), null, [
                'type' => SyncIssue::VOID_REFUSED, 'key' => 'void:'.$item->clientUuid, 'details' => [
                    'sale' => $sale->uuid, 'invoice_number' => $sale->invoice_number, 'till' => $item->register->code,
                    'person' => $person->name, 'recorded_at' => $item->recordedAt->toIso8601String(), 'reason' => $data['reason'],
                    'refusal' => 'sale_has_returns',
                ],
            ]);
        }

        if (! $this->window->allows($sale, $item->recordedAt)) {
            throw new PushRefused(ApiErrorCode::VoidWindowClosed, $this->window->refusal($sale, $item->recordedAt));
        }

        $result = $this->void->execute($sale, $data['reason'], $person, till: $item->register, recordedAt: $item->recordedAt);

        return [
            'sale_uuid' => $sale->uuid,
            'already_voided' => false,
            'items_restocked' => $result['units'],
            'money' => VoidSale::money($result),
            'flags' => [],
        ];
    }
}
