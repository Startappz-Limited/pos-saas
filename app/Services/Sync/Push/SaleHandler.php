<?php

namespace App\Services\Sync\Push;

use App\Actions\Sale\RecordSale;
use App\Actions\Sale\SaleContext;
use App\Enums\ApiErrorCode;
use App\Http\Requests\HeldSaleRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SyncIssue;
use App\Services\Mpesa\TillPayments;
use App\Services\Sync\TradingLease;
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

        $validated = $this->validated($data, $item);
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
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $item->user);
        $request->validateResolved();

        return $request->validated();
    }
}
