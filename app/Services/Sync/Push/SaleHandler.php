<?php

namespace App\Services\Sync\Push;

use App\Actions\Sale\RecordSale;
use App\Actions\Sale\SaleContext;
use App\Casts\Quantity;
use App\Enums\ApiErrorCode;
use App\Http\Requests\HeldSaleRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SyncIssue;
use App\Services\Mpesa\TillPayments;
use App\Services\Sync\TradingLease;
use App\Support\Stock\BaseQuantity;
use App\Support\Sync\SyncIssues;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * A sale a till made, online or offline (Phase 7, decisions 4.5 and 4.9):
 * validated exactly as `POST /api/sales` is, recorded through RecordSale with
 * the till's own time, prices, invoice number and cash session. Accepted only
 * if its time lies inside a lease the server issued to this till. Stock may go
 * negative (flagged); a time in the future is accepted but flagged.
 */
final class SaleHandler implements PushHandler
{
    public function __construct(
        private RecordSale $recordSale,
        private TradingLease $leases,
        private PushPeople $people,
        private SyncIssues $issues,
        private TillPayments $payments,
    ) {}

    public function handle(PushItem $item): array
    {
        if (! $item->accepting('skip_lease') && ! $this->leases->covers($item->register->id, $item->recordedAt)) {
            throw new PushRefused(ApiErrorCode::OutsideTradingLease, __('This sale was recorded outside the time this till was allowed to trade offline.'));
        }

        $data = $item->data;
        // A customer the till created offline is named by its client uuid.
        if (! empty($data['customer_client_uuid'])) {
            // A customer merged into another since (decision 4.9) leads to the one kept.
            $named = Customer::query()->withTrashed()->where('client_uuid', $data['customer_client_uuid'])->first(['id', 'merged_into_id']);
            $data['customer_id'] = ($named?->merged_into_id ?? $named?->id)
                // Push the other item first (stage 10, step 5c): pending, sent again; Phase 7's apps get validation_failed.
                ?? throw new PushPending(ApiErrorCode::CustomerNotSynced, __('The sale\'s customer has not been synced. Push the customer before the sale.'));
        }

        // A unit the server never had is held before the sale is validated, whose own rule would
        // otherwise answer for it (stage 11, step 8).
        $this->refuseUnknownUnits($data['items'] ?? [], $item);

        $validated = $this->validated($data, $item);
        $mismatches = [];
        $validated['items'] = $this->resolveUnits($validated['items'] ?? [], $data['items'] ?? [], $item, $mismatches);
        $cashier = $this->people->staff($data['cashier'] ?? null, $item);
        $session = empty($data['cash_session'])
            ? null
            : CashRegister::query()->where('client_uuid', $data['cash_session'])->where('register_id', $item->register->id)->first();

        try {
            $recorded = $this->recordSale->execute($validated, new SaleContext(
                recordedAt: $item->recordedAt,
                clientUuid: $item->clientUuid,
                shopId: $item->register->shop_id,
                registerId: $item->register->id,
                cashSession: $session,
                invoiceNumber: isset($data['invoice_number']) ? (string) $data['invoice_number'] : null,
                soldBy: $cashier->id,
                awaitingPayment: (bool) ($data['awaiting_mpesa'] ?? false),
                invoiceAsGiven: $item->accepting('invoice_as_given'),
                webSeries: $item->accepting('web_series'),
                // A till's push is never refused for overselling (stage 11 step 4 follow-up,
                // decided 5 November 2026): it can't know what other tills have sold since its
                // last pull.
                enforceStock: false,
            ));
        } catch (InvalidArgumentException $e) {
            throw new PushRefused(ApiErrorCode::InvalidInvoiceNumber, $e->getMessage(), ['invoice_number' => [$e->getMessage()]]);
        } catch (UniqueConstraintViolationException) {
            $number = (string) ($data['invoice_number'] ?? '');

            throw new PushRefused(
                ApiErrorCode::InvoiceNumberTaken,
                __('Another sale already has this invoice number.'),
                ['invoice_number' => [__('Another sale already has this invoice number.')]],
                ['type' => SyncIssue::INVOICE_REPEAT, 'key' => $number, 'details' => [
                    'invoice_number' => $number,
                    'client_uuid' => $item->clientUuid,
                    'recorded_at' => $item->recordedAt->toIso8601String(),
                    'existing_sale' => Sale::query()->where('invoice_number', $number)->value('uuid'),
                ]],
            );
        }

        $sale = $recorded->sale;
        $flags = [];
        // How a held sale was accepted (stage 10, step 5c), kept on the sale.
        // Keyed by flag: `accepted_outside_lease` => true, `rerung` => the till's number and client uuid.
        if (! empty($item->accept['flags'])) {
            $flagged = collect($item->accept['flags'])->mapWithKeys(fn ($value, $key) => is_string($key) ? [$key => $value] : [$value => true])->all();
            $sale->forceFill(['sync_flags' => $flagged])->save();
        }

        // An M-Pesa payment confirmed before the sale arrived is recorded now.
        if ((bool) ($data['awaiting_mpesa'] ?? false)) {
            $this->payments->applyForSale($sale);
            $sale->refresh();
        }
        $about = ['sale' => $sale->uuid, 'invoice_number' => $sale->invoice_number, 'recorded_at' => $item->recordedAt->toIso8601String()];
        foreach ($mismatches as $index => $mismatch) {
            $this->issues->record(SyncIssue::UNIT_FACTOR_MISMATCH, 'unit:'.$sale->uuid.':'.$index, [...$about, ...$mismatch], $item->register);
            $flags[] = 'unit_factor_mismatch';
        }

        if ($recorded->invoiceAdoption !== null) {
            $this->issues->invoiceAdopted($item->register, $recorded->invoiceAdoption);
        }
        if ($item->recordedAt->greaterThan(now()->addMinutes(5))) {
            $flags[] = 'implausible_time';
            $this->issues->record(SyncIssue::IMPLAUSIBLE_TIME, 'sale:'.$sale->uuid, [...$about, 'received_at' => now()->toIso8601String()], $item->register);
        }
        // Sold below zero: the ledger let it through and costed it provisionally.
        if ($this->issues->recordNegativeStock($sale, $item->register, $about)) {
            $flags[] = 'negative_stock';
        }
        // Short only because sellable stock was expired (stage 11, step 5).
        if ($this->issues->recordExpiredStock($sale, $item->register)) {
            $flags[] = 'expired_stock';
        }
        if ($recorded->overCreditLimit) {
            $flags[] = 'over_credit_limit';
        }

        return [
            'sale_uuid' => $sale->uuid,
            'invoice_number' => $sale->invoice_number,
            'total_amount' => number_format((float) $sale->total_amount, 2, '.', ''),
            'payment_status' => $sale->payment_status,
            'flags' => $flags,
        ];
    }

    /**
     * The sale's data through StoreSaleRequest, as the flat API validates it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function validated(array $data, PushItem $item): array
    {
        $class = $item->accepting('as_recorded') ? HeldSaleRequest::class : StoreSaleRequest::class;
        $request = $class::createFrom(Request::create('/', 'POST', $data));
        // The till rang this sale already, possibly in a unit removed or stopped since: it is recorded as sold.
        $request->acceptRecordedUnits();
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $item->user);
        $request->validateResolved();

        return $request->validated();
    }

    /**
     * The unit a raw line names, for its product's variation; null when the server has none of that uuid.
     *
     * @param  array<string, mixed>  $line  the line once validated, or as sent
     * @param  array<string, mixed>  $given  the line as the till sent it
     */
    private function unitOf(array $line, array $given): ?ProductUnit
    {
        if (empty($given['unit_id'])) {
            return null;
        }

        $variation = ! empty($line['variation_id'])
            ? ProductVariation::query()->find($line['variation_id'])
            : Product::query()->find($line['product_id'])?->defaultVariation()->first();

        return $variation !== null ? ProductUnit::resolveFor($variation, (string) $given['unit_id']) : null;
    }

    /**
     * Refuses the sale (stage 11, step 8) when any of its lines names a unit the server never had.
     *
     * @param  list<array<string, mixed>>  $raw  the lines as the till sent them
     *
     * @throws PushRefused
     */
    private function refuseUnknownUnits(array $raw, PushItem $item): void
    {
        foreach ($raw as $line) {
            if (empty($line['unit_id']) || $this->unitOf($line, $line) !== null) {
                continue;
            }

            throw new PushRefused(
                ApiErrorCode::UnknownUnit,
                __('That unit is not known for this product. The sale is kept on the till until someone discards it.'),
                ['items' => [__('Unknown unit.')]],
                ['type' => SyncIssue::UNKNOWN_UNIT, 'key' => (string) $item->clientUuid, 'details' => [
                    'client_uuid' => $item->clientUuid,
                    'unit_id' => (string) $line['unit_id'],
                    'product_id' => $line['product_id'] ?? null,
                    'variation_id' => $line['variation_id'] ?? null,
                    'quantity' => (string) ($line['quantity'] ?? ''),
                    'recorded_at' => $item->recordedAt->toIso8601String(),
                    'till' => $item->register->code,
                ]],
            );
        }
    }

    /**
     * Each line's unit (stage 11, step 8). The server resolves the unit the till sent, for that
     * product's variation, business-scoped and soft-deleted units included: a unit it never had
     * (another business's, another variation's, or a made-up uuid) is refused as `unknown_unit`,
     * a hold, so the till keeps the sale until someone discards it. A line with a base quantity
     * from the till keeps the till's figure, which is what the customer saw and what left the
     * shelf; where it differs from quantity times the unit's factor, the difference is recorded.
     *
     * @param  list<array<string, mixed>>  $items  the validated lines
     * @param  list<array<string, mixed>>  $raw  the lines as the till sent them
     * @param  list<array<string, mixed>>  $mismatches  filled with each line whose figures disagree
     * @return list<array<string, mixed>>
     *
     * @throws PushRefused when a line names a unit the server never had
     */
    private function resolveUnits(array $items, array $raw, PushItem $item, array &$mismatches): array
    {
        foreach ($items as $index => $line) {
            $given = $raw[$index] ?? [];
            $variation = ! empty($line['variation_id'])
                ? ProductVariation::query()->find($line['variation_id'])
                : Product::query()->find($line['product_id'])?->defaultVariation()->first();

            if (empty($given['unit_id'])) {
                continue;
            }

            $unit = $this->unitOf($line, $given);

            if (isset($given['base_quantity']) && $given['base_quantity'] !== '') {
                $quantity = Quantity::normalize($line['quantity']);
                $expected = BaseQuantity::of($quantity, Quantity::normalize($unit->factor));
                if ($expected === null) {
                    throw new PushRefused(ApiErrorCode::ValidationFailed, __('The quantity is too small to sell in this unit.'), ['items' => [__('Quantity is too small for its unit.')]]);
                }
                // The till's own figure is taken as sent only when it is a plain, non-negative number:
                // anything else is refused as a validation failure, not stored.
                if (! is_numeric($given['base_quantity']) || (float) $given['base_quantity'] < 0 || (float) $given['base_quantity'] > 10000000) {
                    throw new PushRefused(ApiErrorCode::ValidationFailed, __('The base quantity is not a valid quantity.'), ['items' => [__('Base quantity is invalid.')]]);
                }

                $tillFigure = Quantity::normalize($given['base_quantity']);

                // A positive sale must move stock: a till's zero (even one that agrees with a zero expectation)
                // would record a sale that removes nothing.
                if (bccomp($tillFigure, '0', 3) <= 0) {
                    throw new PushRefused(ApiErrorCode::ValidationFailed, __('The base quantity must be above zero.'), ['items' => [__('Base quantity must be above zero.')]]);
                }

                if (bccomp($expected, $tillFigure, 3) !== 0) {
                    $mismatches[$index] = [
                        'unit' => $unit->name,
                        'quantity' => $quantity,
                        'factor' => Quantity::normalize($unit->factor),
                        'till_base_quantity' => $tillFigure,
                        'server_base_quantity' => $expected,
                    ];
                }

                $line['base_quantity'] = $tillFigure;
            }

            $items[$index] = $line;
        }

        return $items;
    }
}
