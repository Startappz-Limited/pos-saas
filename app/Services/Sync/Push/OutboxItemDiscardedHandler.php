<?php

namespace App\Services\Sync\Push;

use App\Enums\ApiErrorCode;
use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Models\SyncIssue;
use App\Models\SyncPushReceipt;
use App\Support\Sync\SyncIssues;
use Illuminate\Support\Facades\Validator;

/**
 * A till abandons an item in its outbox (stage 10, step 5c): a held or stuck
 * sale, void, payment or other item, with a reason and the person who decided.
 * Applied once per its own client uuid. The discarded item gets a `discarded`
 * receipt, so if it is pushed again it answers `discarded`; the discard goes on
 * the till sync review (`outbox_item_discarded`, with the raw item) and in the
 * audit trail, so nothing a till abandons goes unrecorded.
 *
 * The person needs `sales.void` to discard a `sale` or `sale_payment` (goods or
 * money the till recorded), and `sync-issues.discard` for anything else;
 * otherwise `forbidden` (drop). An item the server already applied is a no-op
 * (`already_applied`), so the till clears it.
 */
final class OutboxItemDiscardedHandler implements PushHandler
{
    public function __construct(private PushPeople $people, private SyncIssues $issues) {}

    public function handle(PushItem $item): array
    {
        $data = Validator::make($item->data, [
            'item_type' => ['required', 'string', 'max:40'],
            'client_uuid' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:500'],
            'discarded_by' => ['nullable', 'uuid'],
            'item' => ['nullable', 'array'],
        ])->validate();

        $person = $this->people->staff($data['discarded_by'] ?? null, $item, 'discarded_by');
        $money = in_array($data['item_type'], ['sale', 'sale_payment'], true);
        $allowed = $money ? ($person->can('sales.void') || $person->can('sales.full-access')) : $person->can('sync-issues.discard');
        if (! $allowed) {
            throw new PushRefused(ApiErrorCode::Forbidden, $money
                ? __('Discarding a sale or a payment needs the void permission.')
                : __('That person can\'t discard till items.'));
        }

        $existing = SyncPushReceipt::query()->where('client_uuid', $data['client_uuid'])->first();
        if ($existing !== null && $existing->outcome !== SyncPushReceipt::DISCARDED) {
            return ['client_uuid' => $data['client_uuid'], 'already_applied' => true, 'flags' => []];
        }
        if ($existing === null) {
            SyncPushReceipt::create([
                'register_id' => $item->register->id, 'client_uuid' => $data['client_uuid'], 'type' => $data['item_type'],
                'result' => ['reason' => $data['reason'], 'discarded_by' => $person->name], 'outcome' => SyncPushReceipt::DISCARDED, 'applied_at' => now(),
            ]);
        }

        $details = [
            'till' => $item->register->code, 'item_type' => $data['item_type'], 'client_uuid' => $data['client_uuid'],
            'item' => $data['item'] ?? null, 'reason' => $data['reason'], 'person' => $person->name, 'recorded_at' => $item->recordedAt->toIso8601String(),
        ];
        $this->issues->record(SyncIssue::OUTBOX_ITEM_DISCARDED, 'discarded:'.$data['client_uuid'], $details, $item->register);
        // A held item it settles is resolved with it.
        SyncIssue::query()->where('type', SyncIssue::HELD_ITEM)->where('key', 'held:'.$data['client_uuid'])->whereNull('resolved_at')
            ->get()->each(fn (SyncIssue $held) => $held->update(['resolved_at' => now(), 'resolved_by' => $person->id, 'resolution' => 'discarded']));

        AuditableEvent::dispatch(
            auditableType: SyncPushReceipt::class, auditableId: null, auditableUuid: $data['client_uuid'], event: AuditEvent::DELETED,
            newValues: $details, userId: $person->id, userName: $person->name, shopId: (int) $item->register->shop_id,
            tags: ['sync', 'outbox_item_discarded', $data['item_type']], businessId: (int) $item->register->shop?->business_id,
        );

        return ['client_uuid' => $data['client_uuid'], 'already_applied' => false, 'flags' => []];
    }
}
