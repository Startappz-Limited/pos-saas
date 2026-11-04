<?php

namespace App\Support\Sync;

use App\Enums\ApiErrorCode;
use App\Enums\AuditEvent;
use App\Events\AuditableEvent;
use App\Exceptions\BusinessRuleException;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SyncIssue;
use App\Models\SyncPushReceipt;
use App\Models\User;
use App\Services\InvoiceNumberService;
use App\Services\Sync\Push\PushPending;
use App\Services\Sync\Push\PushRefused;
use App\Services\Sync\SyncPush;
use App\Support\Sales\VoidWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Held till items on the till sync review (stage 10, step 5c): what each can
 * be resolved by, and doing it. A held item is applied ("accepted") through
 * its own handler with the fix its code needs, or discarded with a reason;
 * either way its client uuid gets a receipt (`accepted` or `discarded`), so
 * the till's next push of it, or its pull's `resolved_items`, clears it.
 * Neither can be done twice (the issue is locked); both are audited.
 *
 * - `outside_trading_lease`: accept, applied at the till's recorded time and
 *   flagged `accepted_outside_lease` (a void only if the window allows it now
 *   and the sale has no return that isn't rejected); else discard;
 * - `invalid_invoice_number`: accept with the till's number when it is unique
 *   in the business, flagged `accepted_invalid_series`; never a web-series
 *   number (discard only);
 * - `invoice_number_taken`: discard only, or "discard and record as a new web
 *   sale" (re-rung, keeping the till's client uuid);
 * - `validation_failed`: by the rule that failed (the stage file's table):
 *   correctable (the review offers the correction), accept as recorded
 *   (flagged), or discard only;
 * - anything else (`unknown_item_type`, `unknown_unit`, …): discard only.
 *
 * Accepting needs `sync-issues.accept`. Discarding needs `sales.void` for a
 * `sale` or `sale_payment` item, and `sync-issues.discard` otherwise.
 */
final class HeldItems
{
    /** Fields a manager can correct on the review, and what they are corrected with. */
    public const CORRECTABLE = [
        'customer_id' => 'customer', 'customer_client_uuid' => 'customer', 'walk_in_customer_name' => 'walk_in',
        'walk_in_customer_phone' => 'walk_in', 'payment_method' => 'payment_method', 'source_id' => 'source',
        'delivery_location' => 'delivery_location', 'delivery_company_id' => 'delivery_company', 'customer_tax_pin' => 'tax_pin',
        'variation_id' => 'variation', 'cashier' => 'cashier',
    ];

    /** Text cut to its column when accepted as recorded. */
    private const TEXT_LIMITS = ['delivery_location' => 1000, 'notes' => 1000, 'expense_notes' => 500, 'walk_in_customer_name' => 255, 'walk_in_customer_phone' => 20];

    public function __construct(private SyncPush $push, private InvoiceNumberService $numbers, private VoidWindow $window) {}

    /**
     * What the review offers for the item.
     *
     * @return array{accept: bool, as_recorded: bool, corrections: list<string>, rerung: bool, discard: bool, reason: string}
     */
    public function options(SyncIssue $issue): array
    {
        $details = $issue->details;
        $type = (string) ($details['item_type'] ?? '');
        $none = ['accept' => false, 'as_recorded' => false, 'corrections' => [], 'rerung' => false, 'discard' => true];

        switch ($details['code'] ?? null) {
            case ApiErrorCode::OutsideTradingLease->value:
                if ($type === 'sale_void') {
                    $sale = $this->voidedSale($details);
                    $ok = $sale !== null && $sale->status !== 'voided' && ! VoidWindow::hasOpenReturns($sale) && $this->window->allows($sale, now());

                    return [...$none, 'accept' => $ok, 'reason' => $ok ? __('Recorded outside its trading lease. Accept to void the sale now.') : __('Recorded outside its trading lease, and the sale can\'t be voided now. Discard it.')];
                }

                return [...$none, 'accept' => true, 'reason' => __('Recorded outside its trading lease. Accept to record it at the time the till recorded it.')];

            case ApiErrorCode::InvalidInvoiceNumber->value:
                $number = (string) ($details['item']['invoice_number'] ?? '');
                $ok = $number !== '' && ! $this->numbers->isWebNumber((int) $issue->shop_id, $number) && ! Sale::withTrashed()->where('invoice_number', $number)->exists();

                return [...$none, 'accept' => $ok, 'reason' => $ok ? __('Its number isn\'t in this till\'s series. Accept to keep the till\'s number.') : __('Its number is in the web series or already used. Discard it.')];

            case ApiErrorCode::InvoiceNumberTaken->value:
                return [...$none, 'rerung' => $type === 'sale', 'reason' => __('Its invoice number is already taken, so it can\'t be kept, and a printed number is never changed. If the sale happened, record it as a new web sale; otherwise discard it.')];

            case ApiErrorCode::ValidationFailed->value:
                return [...$none, ...$this->validationOptions((array) ($details['errors'] ?? []), (array) ($details['item'] ?? []))];

            default:
                return [...$none, 'reason' => __('This item can only be discarded.')];
        }
    }

    /**
     * Applies the item, with the manager's corrections (only fields the review
     * offers), at the till's recorded time.
     *
     * @param  array<string, mixed>  $corrections
     *
     * @throws BusinessRuleException when it can't be accepted (the reason is shown)
     */
    public function accept(SyncIssue $issue, User $by, array $corrections = []): array
    {
        return $this->settle($issue, function (SyncIssue $issue) use ($by, $corrections): array {
            $options = $this->options($issue);
            if (! $options['accept'] && ! $options['as_recorded'] && $options['corrections'] === []) {
                throw new BusinessRuleException(__('This item can\'t be accepted, only discarded.'));
            }

            $details = $issue->details;
            $data = (array) ($details['item'] ?? []);
            $accept = ['flags' => []];
            switch ($details['code']) {
                case ApiErrorCode::OutsideTradingLease->value:
                    $accept['skip_lease'] = true;
                    $accept['flags'][] = 'accepted_outside_lease';
                    break;
                case ApiErrorCode::InvalidInvoiceNumber->value:
                    $accept['invoice_as_given'] = true;
                    $accept['flags'][] = 'accepted_invalid_series';
                    break;
                case ApiErrorCode::ValidationFailed->value:
                    [$data, $flags] = $this->corrected($data, $corrections, $options);
                    $accept['as_recorded'] = $options['as_recorded'];
                    $accept['flags'] = $flags;
                    break;
            }

            $result = $this->apply($issue, $by, $data, $accept);

            return ['resolution' => 'accepted', 'result' => $result];
        }, $by, 'accepted');
    }

    /**
     * Discards the item with a reason: it is never applied, and the till clears it.
     */
    public function discard(SyncIssue $issue, User $by, string $reason): array
    {
        if (trim($reason) === '') {
            throw new BusinessRuleException(__('Say why it is discarded.'));
        }

        return $this->settle($issue, function (SyncIssue $issue) use ($by, $reason): array {
            $this->receipt($issue, SyncPushReceipt::DISCARDED, ['reason' => $reason, 'discarded_by' => $by->name]);

            return ['resolution' => 'discarded', 'reason' => $reason];
        }, $by, 'discarded');
    }

    /**
     * "Discard and record as a new web sale" (decided 28 October 2026): a sale
     * whose till number was taken, which happened, is created from the raw
     * item in the shop's web series with its lines, stock, payments, customer,
     * cashier and recorded time, flagged `rerung` with the till's number and
     * client uuid; the till's item is discarded, while the sale takes its
     * client uuid, so items queued behind it apply to the new sale.
     */
    public function rerung(SyncIssue $issue, User $by): array
    {
        return $this->settle($issue, function (SyncIssue $issue) use ($by): array {
            if (! $this->options($issue)['rerung']) {
                throw new BusinessRuleException(__('Only a sale whose invoice number was taken can be re-rung.'));
            }

            $data = (array) ($issue->details['item'] ?? []);
            $tillNumber = (string) ($data['invoice_number'] ?? '');
            unset($data['invoice_number']);

            $result = $this->apply($issue, $by, $data, [
                'web_series' => true,
                'flags' => ['rerung' => ['invoice_number' => $tillNumber, 'client_uuid' => $issue->details['client_uuid']]],
            ]);
            SyncPushReceipt::query()->where('client_uuid', $issue->details['client_uuid'])->update(['outcome' => SyncPushReceipt::DISCARDED]);

            return ['resolution' => 'rerung', 'result' => $result, 'new_sale' => $result['sale_uuid'] ?? null, 'till_invoice_number' => $tillNumber];
        }, $by, 'rerung');
    }

    public function mayDiscard(User $user, SyncIssue $issue): bool
    {
        $type = (string) ($issue->details['item_type'] ?? '');

        return in_array($type, ['sale', 'sale_payment'], true)
            ? ($user->can('sales.void') || $user->can('sales.full-access'))
            : $user->can('sync-issues.discard');
    }

    /**
     * @param  callable(SyncIssue): array<string, mixed>  $work
     */
    private function settle(SyncIssue $issue, callable $work, ?User $by = null, ?string $what = null): array
    {
        return DB::transaction(function () use ($issue, $work, $by): array {
            $issue = SyncIssue::query()->whereKey($issue->id)->lockForUpdate()->firstOrFail();
            if ($issue->type !== SyncIssue::HELD_ITEM || $issue->resolved_at !== null) {
                throw new BusinessRuleException(__('This item has already been resolved.'));
            }

            $outcome = $work($issue);
            $issue->update([
                'resolved_at' => now(), 'resolved_by' => $by?->id ?? auth()->id(), 'resolution' => $outcome['resolution'],
                'details' => [...$issue->details, 'outcome' => $outcome],
            ]);

            AuditableEvent::dispatch(
                auditableType: SyncIssue::class, auditableId: $issue->id, auditableUuid: (string) $issue->uuid, event: AuditEvent::UPDATED,
                newValues: ['held_item' => $issue->details['client_uuid'] ?? null, 'item_type' => $issue->details['item_type'] ?? null, 'item' => $issue->details['item'] ?? null, 'outcome' => $outcome],
                userId: $by?->id ?? auth()->id(), userName: $by?->name ?? auth()->user()?->name, shopId: $issue->shop_id === null ? null : (int) $issue->shop_id,
                tags: ['sync', 'held_item', $outcome['resolution']], businessId: (int) $issue->business_id,
            );

            return $outcome;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $accept
     * @return array<string, mixed>
     */
    private function apply(SyncIssue $issue, User $by, array $data, array $accept): array
    {
        $register = Register::query()->findOrFail($issue->register_id);
        $raw = ['client_uuid' => (string) $issue->details['client_uuid'], 'type' => (string) $issue->details['item_type'], 'recorded_at' => (string) $issue->details['recorded_at'], 'data' => $data];

        try {
            return $this->push->applyAccepted($register, $by, $raw, $accept);
        } catch (PushRefused|PushPending $e) {
            throw new BusinessRuleException(__('It still can\'t be applied: :reason', ['reason' => $e->getMessage()]));
        } catch (ValidationException $e) {
            throw new BusinessRuleException(__('It still can\'t be applied: :reason', ['reason' => collect($e->errors())->flatten()->implode(' ')]));
        }
    }

    /**
     * @param  array<string, mixed>|null  $result
     */
    private function receipt(SyncIssue $issue, string $outcome, ?array $result): void
    {
        SyncPushReceipt::query()->updateOrCreate(
            ['client_uuid' => (string) $issue->details['client_uuid']],
            ['register_id' => $issue->register_id, 'type' => (string) $issue->details['item_type'], 'result' => $result, 'outcome' => $outcome, 'applied_at' => now()],
        );
    }

    /**
     * Which group the failed rules fall in (the stage file's table).
     *
     * @param  array<string, list<string>>  $errors
     * @param  array<string, mixed>  $item
     * @return array{accept: bool, as_recorded: bool, corrections: list<string>, reason: string}
     */
    private function validationOptions(array $errors, array $item): array
    {
        $corrections = [];
        $asRecorded = false;

        foreach (array_keys($errors) as $field) {
            $base = preg_replace('/^items\.\d+\./', '', (string) $field);
            $value = data_get($item, (string) $field);

            if (isset(self::CORRECTABLE[$base])) {
                $corrections[] = self::CORRECTABLE[$base];
            } elseif (in_array($base, ['quantity', 'price'], true) && is_numeric($value) && ((float) $value > ($base === 'quantity' ? 10000 : 1000000))) {
                $asRecorded = true;
            } elseif (in_array($base, ['delivery_fee', 'packaging_fee', 'other_expenses', 'tax_amount'], true) && is_numeric($value) && (float) $value >= 0) {
                $asRecorded = true;
            } elseif ($base === 'walk_in_customer_email' || (isset(self::TEXT_LIMITS[$base]) && is_string($value) && mb_strlen($value) > self::TEXT_LIMITS[$base])) {
                $asRecorded = true;
            } else {
                // items missing, a product not of the business, a quantity below 1,
                // a negative amount, a discount above the subtotal: discard only.
                return ['accept' => false, 'as_recorded' => false, 'corrections' => [], 'reason' => __('It can\'t be accepted: :fields. Discard it, and ring the sale up again on the web if it happened.', ['fields' => implode(' ', array_map(fn ($messages) => implode(' ', (array) $messages), $errors))])];
            }
        }

        return [
            'accept' => $corrections === [],
            'as_recorded' => $asRecorded,
            'corrections' => array_values(array_unique($corrections)),
            'reason' => $corrections !== [] ? __('Correct what the till sent, then accept it.') : __('Accept it as the till recorded it; it will be flagged.'),
        ];
    }

    /**
     * The item with the manager's corrections, and, accepted as recorded, a bad
     * email dropped and long text cut to its column.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $corrections
     * @param  array<string, mixed>  $options
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function corrected(array $data, array $corrections, array $options): array
    {
        $flags = [];
        $allowed = $options['corrections'];

        if (in_array('customer', $allowed, true) && array_key_exists('customer_id', $corrections)) {
            unset($data['customer_client_uuid']);
            $data['customer_id'] = $corrections['customer_id'] ?: null;
            if (! $data['customer_id']) {
                $allowed[] = 'walk_in';
            }
        }
        foreach (['walk_in' => ['walk_in_customer_name', 'walk_in_customer_phone'], 'payment_method' => ['payment_method'], 'source' => ['source_id'],
            'delivery_location' => ['delivery_location'], 'delivery_company' => ['delivery_company_id'], 'tax_pin' => ['customer_tax_pin'], 'cashier' => ['cashier']] as $group => $fields) {
            if (in_array($group, $allowed, true)) {
                foreach ($fields as $field) {
                    if (array_key_exists($field, $corrections)) {
                        $data[$field] = $corrections[$field] === '' ? null : $corrections[$field];
                    }
                }
            }
        }
        if (in_array('variation', $allowed, true)) {
            foreach ((array) ($corrections['variations'] ?? []) as $index => $variationId) {
                if (isset($data['items'][$index])) {
                    $data['items'][$index]['variation_id'] = $variationId;
                }
            }
        }

        if ($options['as_recorded']) {
            if (! empty($data['walk_in_customer_email']) && filter_var($data['walk_in_customer_email'], FILTER_VALIDATE_EMAIL) === false) {
                unset($data['walk_in_customer_email']);
                $flags[] = 'bad_email';
            }
            foreach (self::TEXT_LIMITS as $field => $limit) {
                if (is_string($data[$field] ?? null) && mb_strlen($data[$field]) > $limit) {
                    $data[$field] = mb_substr($data[$field], 0, $limit);
                    $flags[] = 'text_shortened';
                }
            }
            foreach ((array) ($data['items'] ?? []) as $line) {
                if ((float) ($line['quantity'] ?? 0) > 10000 || (float) ($line['price'] ?? 0) > 1000000) {
                    $flags[] = 'large_line';
                }
            }
            foreach (['delivery_fee', 'packaging_fee', 'other_expenses'] as $field) {
                if ((float) ($data[$field] ?? 0) > 10000) {
                    $flags[] = 'large_amount';
                }
            }
            if ((float) ($data['tax_amount'] ?? 0) > 100000) {
                $flags[] = 'large_amount';
            }
        }

        return [$data, array_values(array_unique($flags))];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function voidedSale(array $details): ?Sale
    {
        $item = (array) ($details['item'] ?? []);

        return Sale::query()
            ->when($item['sale'] ?? null, fn ($query, $uuid) => $query->where('uuid', $uuid))
            ->when(! ($item['sale'] ?? null), fn ($query) => $query->where('client_uuid', $item['sale_client_uuid'] ?? ''))
            ->first();
    }
}
