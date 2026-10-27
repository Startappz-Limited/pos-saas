<?php

namespace App\Services\Sync;

use App\Enums\ApiErrorCode;
use App\Exceptions\SubscriptionInactive;
use App\Models\Register;
use App\Models\SyncPushReceipt;
use App\Models\User;
use App\Services\Billing\Entitlements;
use App\Services\Sync\Push\CashSessionHandler;
use App\Services\Sync\Push\CustomerHandler;
use App\Services\Sync\Push\MpesaCodeHandler;
use App\Services\Sync\Push\PaymentHandler;
use App\Services\Sync\Push\PushHandler;
use App\Services\Sync\Push\PushItem;
use App\Services\Sync\Push\PushRefused;
use App\Services\Sync\Push\SaleHandler;
use App\Services\Sync\Push\TillSignInHandler;
use App\Support\Sync\SyncIssues;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The till's sync push (Phase 7, decision 4.5): its outbox of sales,
 * payments, new customers, cash-session events and PIN sign-ins, applied in
 * the order sent, each on its own, with a result per item:
 *
 * - `applied`: done now; `duplicate`: done before (same client uuid), with
 *   the result it had then, so a batch pushed twice is applied once;
 * - `refused`: not done, with a code; nothing is kept, so the till may push it
 *   again once the reason is gone;
 * - `failed`: an error on the server; nothing is kept either.
 *
 * One item's refusal doesn't stop the rest. Every item is applied for the
 * till's own business and register; what it names is looked up through the
 * tenant scopes, so another business's records can't be reached.
 */
class SyncPush
{
    /** @var array<string, class-string<PushHandler>> */
    public const HANDLERS = [
        'customer' => CustomerHandler::class,
        'sale' => SaleHandler::class,
        'sale_payment' => PaymentHandler::class,
        'cash_session' => CashSessionHandler::class,
        'till_sign_in' => TillSignInHandler::class,
        'mpesa_code' => MpesaCodeHandler::class,
    ];

    /**
     * @param  list<array{client_uuid: string, type: string, recorded_at: string, data?: array<string, mixed>}>  $items
     * @return list<array<string, mixed>>
     */
    public function push(Register $register, User $user, array $items): array
    {
        return array_map(fn (array $raw): array => $this->apply($register, $user, $raw), $items);
    }

    /**
     * @param  array{client_uuid: string, type: string, recorded_at: string, data?: array<string, mixed>}  $raw
     * @return array<string, mixed>
     */
    private function apply(Register $register, User $user, array $raw): array
    {
        $base = ['client_uuid' => $raw['client_uuid'], 'type' => $raw['type']];

        $done = SyncPushReceipt::query()->where('client_uuid', $raw['client_uuid'])->first();
        if ($done !== null) {
            return [...$base, 'status' => 'duplicate', 'result' => $done->result];
        }

        $handler = self::HANDLERS[$raw['type']] ?? null;
        if ($handler === null) {
            return [...$base, 'status' => 'refused', 'code' => ApiErrorCode::UnknownItemType->value, 'message' => __('Unknown item type.')];
        }

        $item = new PushItem($raw['client_uuid'], $raw['type'], Carbon::parse($raw['recorded_at']), $raw['data'] ?? [], $register, $user);

        if (($refusal = $this->readOnlyRefusal($item)) !== null) {
            return [...$base, ...$refusal];
        }

        try {
            $result = DB::transaction(function () use ($handler, $item, $register): array {
                $result = app($handler)->handle($item);

                SyncPushReceipt::create([
                    'register_id' => $register->id,
                    'client_uuid' => $item->clientUuid,
                    'type' => $item->type,
                    'result' => $result,
                    'applied_at' => now(),
                ]);

                return $result;
            });

            return [...$base, 'status' => 'applied', 'result' => $result];
        } catch (PushRefused $e) {
            // Outside the rolled-back transaction, so the issue is kept for review.
            if ($e->issue !== null) {
                app(SyncIssues::class)->record($e->issue['type'], $e->issue['key'], $e->issue['details'], $register);
            }

            return [...$base, 'status' => 'refused', 'code' => $e->errorCode->value, 'message' => $e->getMessage(), 'errors' => $e->errors];
        } catch (ValidationException $e) {
            return [...$base, 'status' => 'refused', 'code' => ApiErrorCode::ValidationFailed->value, 'message' => $e->getMessage(), 'errors' => $e->errors()];
        } catch (UniqueConstraintViolationException $e) {
            // The same item pushed twice at once: the other push applied it.
            $done = SyncPushReceipt::query()->where('client_uuid', $item->clientUuid)->first();
            if ($done !== null) {
                return [...$base, 'status' => 'duplicate', 'result' => $done->result];
            }

            return $this->failed($base, $e);
        } catch (Throwable $e) {
            return $this->failed($base, $e);
        }
    }

    /**
     * While the business is read-only (decision 4.9): what the till recorded
     * before the subscription lapsed is still accepted; anything recorded
     * after, or anything at all while it has more shops than it pays for, is
     * refused and stays on the till until the business is paid up. PIN
     * sign-ins are always accepted, so they are always audited.
     *
     * @return array<string, mixed>|null
     */
    private function readOnlyRefusal(PushItem $item): ?array
    {
        if ($item->type === 'till_sign_in') {
            return null;
        }

        $entitlements = app(Entitlements::class);
        $reason = $entitlements->readOnlyReason();

        if ($reason === null) {
            return null;
        }

        $lapsedAt = $entitlements->lapsedAt();
        if ($reason === SubscriptionInactive::LAPSED && $lapsedAt !== null && $item->recordedAt->lessThan($lapsedAt)) {
            return null;
        }

        return [
            'status' => 'refused',
            'code' => ApiErrorCode::SubscriptionInactive->value,
            'message' => __('This business is read-only until its subscription is paid. Keep this on the till and sync again once it is.'),
        ];
    }

    /**
     * @param  array<string, string>  $base
     * @return array<string, mixed>
     */
    private function failed(array $base, Throwable $e): array
    {
        Log::error('Sync push item failed', [...$base, 'exception' => $e]);

        return [...$base, 'status' => 'failed', 'code' => ApiErrorCode::ServerError->value, 'message' => __('The server could not apply this item. Try again later.')];
    }
}
