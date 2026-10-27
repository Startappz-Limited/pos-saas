<?php

namespace App\Services\Sync\Push;

use App\Actions\Sale\RecordSalePayment;
use App\Enums\ApiErrorCode;
use App\Models\Sale;
use Illuminate\Support\Facades\Validator;

/**
 * A payment a till took against a sale (Phase 7): a credit or cash-on-delivery
 * sale paid later, recorded through RecordSalePayment with the till's own time.
 * The sale is named by its uuid, or by its client uuid when the till made it
 * offline too.
 */
final class PaymentHandler implements PushHandler
{
    public function __construct(private RecordSalePayment $recordPayment, private PushPeople $people) {}

    public function handle(PushItem $item): array
    {
        $data = Validator::make($item->data, [
            'sale' => ['required_without:sale_client_uuid', 'nullable', 'uuid'],
            'sale_client_uuid' => ['required_without:sale', 'nullable', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:cash,card,bank_transfer,mobile_money,cheque'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'cashier' => ['nullable', 'uuid'],
        ])->validate();

        $sale = Sale::query()
            ->when($data['sale'] ?? null, fn ($query, $uuid) => $query->where('uuid', $uuid))
            ->when(! ($data['sale'] ?? null), fn ($query) => $query->where('client_uuid', $data['sale_client_uuid']))
            ->first() ?? throw new PushRefused(ApiErrorCode::NotFound, __('No such sale in this shop. Push the sale before its payment.'));

        if ($sale->payment_status === 'paid') {
            throw new PushRefused(ApiErrorCode::SaleAlreadyPaid, __('This sale is already fully paid.'));
        }

        $cashier = $this->people->staff($data['cashier'] ?? null, $item);
        $sale = $this->recordPayment->execute($sale, [[
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]], $item->recordedAt, $item->clientUuid, $cashier->id);

        return [
            'sale_uuid' => $sale->uuid,
            'payment_status' => $sale->payment_status,
            'balance_due' => number_format((float) $sale->balance_due, 2, '.', ''),
            'flags' => [],
        ];
    }
}
