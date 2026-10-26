<?php

namespace App\Services\Sync\Push;

use App\Actions\Sale\RecordSale;
use App\Actions\Sale\SaleContext;
use App\Enums\ApiErrorCode;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\SyncIssue;
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
    ) {}

    public function handle(PushItem $item): array
    {
        if (! $this->leases->covers($item->register->id, $item->recordedAt)) {
            throw new PushRefused(ApiErrorCode::OutsideTradingLease, __('This sale was recorded outside the time this till was allowed to trade offline.'));
        }

        $data = $item->data;
        // A customer the till created offline is named by its client uuid.
        if (! empty($data['customer_client_uuid'])) {
            // A customer merged into another since (decision 4.9) leads to the one kept.
            $named = Customer::query()->withTrashed()->where('client_uuid', $data['customer_client_uuid'])->first(['id', 'merged_into_id']);
            $data['customer_id'] = ($named?->merged_into_id ?? $named?->id)
                ?? throw new PushRefused(ApiErrorCode::ValidationFailed, __('The sale\'s customer has not been synced.'), ['customer_client_uuid' => [__('Push the customer before the sale.')]]);
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
        $about = ['sale' => $sale->uuid, 'invoice_number' => $sale->invoice_number, 'recorded_at' => $item->recordedAt->toIso8601String()];

        if ($recorded->invoiceAdoption !== null) {
            $this->issues->invoiceAdopted($item->register, $recorded->invoiceAdoption);
        }
        if ($item->recordedAt->greaterThan(now()->addMinutes(5))) {
            $flags[] = 'implausible_time';
            $this->issues->record(SyncIssue::IMPLAUSIBLE_TIME, 'sale:'.$sale->uuid, [...$about, 'received_at' => now()->toIso8601String()], $item->register);
        }
        // Sold below zero: the ledger let it through and costed it provisionally.
        if (StockMovement::query()->where('reference_uuid', $sale->uuid)->where('quantity_after', '<', 0)->exists()) {
            $flags[] = 'negative_stock';
            $this->issues->record(SyncIssue::NEGATIVE_STOCK, 'sale:'.$sale->uuid, $about, $item->register);
        }
        if ($recorded->overCreditLimit) {
            $flags[] = 'over_credit_limit';
        }

        return [
            'sale_uuid' => $sale->uuid,
            'invoice_number' => $sale->invoice_number,
            'total_amount' => number_format((float) $sale->total_amount, 2, '.', ''),
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
        $request = StoreSaleRequest::createFrom(Request::create('/', 'POST', $data));
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $item->user);
        $request->validateResolved();

        return $request->validated();
    }
}
