<?php

namespace App\Services\Sync\Push;

use App\Enums\ApiErrorCode;
use App\Models\CashRegister;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * A cash session opened or closed on a till (Phase 7), with the till's own
 * times. Opening names the session by the item's client uuid; closing names
 * the session it closes.
 */
final class CashSessionHandler implements PushHandler
{
    public function __construct(private PushPeople $people) {}

    public function handle(PushItem $item): array
    {
        $data = Validator::make($item->data, [
            'action' => ['required', Rule::in(['open', 'close'])],
            'session' => ['required_if:action,close', 'nullable', 'uuid'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'closing_balance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'cashier' => ['nullable', 'uuid'],
        ])->validate();

        $cashier = $this->people->staff($data['cashier'] ?? null, $item);

        if ($data['action'] === 'open') {
            $session = CashRegister::openRegister(
                shopId: $item->register->shop_id,
                openingBalance: $data['opening_balance'] ?? 0,
                notes: $data['notes'] ?? null,
                registerId: $item->register->id,
            );
            $session->forceFill([
                'client_uuid' => $item->clientUuid,
                'user_id' => $cashier->id,
                'opened_at' => $item->recordedAt,
                'register_date' => $item->recordedAt->toDateString(),
            ])->save();

            return ['session_uuid' => $session->uuid, 'status' => 'open', 'flags' => []];
        }

        $session = CashRegister::query()->where('client_uuid', $data['session'])->where('register_id', $item->register->id)->first()
            ?? throw new PushRefused(ApiErrorCode::NotFound, __('No such cash session on this till. Push its opening first.'));

        if ($session->isClosed()) {
            return ['session_uuid' => $session->uuid, 'status' => 'closed', 'flags' => ['already_closed']];
        }

        $session->closeRegister($data['closing_balance'] ?? null, $data['notes'] ?? null);
        $session->forceFill(['closed_at' => $item->recordedAt, 'closed_by' => $cashier->id])->save();

        return [
            'session_uuid' => $session->uuid,
            'status' => 'closed',
            'expected_balance' => number_format((float) $session->expected_balance, 2, '.', ''),
            'variance' => number_format((float) $session->variance, 2, '.', ''),
            'flags' => [],
        ];
    }
}
