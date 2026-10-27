<?php

namespace App\Services\Sync\Push;

use App\Actions\User\ManageTillPin;
use App\Enums\ApiErrorCode;
use App\Enums\AuditEvent;
use App\Enums\AuditStatus;
use App\Events\AuditableEvent;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Sign-ins with a till PIN, reported by the till (Phase 7, decided 26 October
 * 2026): each one, right or wrong, every lockout and every unlock goes into the
 * audit trail with the person, the till and the time it happened. When the
 * till locks someone out until a manager unlocks them, the server keeps that
 * lock, so the business's other tills lock them too; an unlock by someone
 * allowed to unlock ends it.
 */
final class TillSignInHandler implements PushHandler
{
    public const OUTCOMES = ['signed_in', 'failed', 'locked_out', 'locked_out_until_unlocked', 'unlocked'];

    public function __construct(private PushPeople $people, private ManageTillPin $pins) {}

    public function handle(PushItem $item): array
    {
        $data = Validator::make($item->data, [
            'user' => ['required', 'uuid'],
            'outcome' => ['required', Rule::in(self::OUTCOMES)],
            'unlocked_by' => ['required_if:outcome,unlocked', 'nullable', 'uuid'],
            'failed_attempts' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'lock_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ])->validate();

        $person = $this->people->staff($data['user'], $item, 'user');
        $actor = $person;

        if ($data['outcome'] === 'unlocked') {
            $actor = $this->people->staff($data['unlocked_by'], $item, 'unlocked_by');
            if (! $actor->can((string) config('sync.pin_unlock_permission'))) {
                throw new PushRefused(ApiErrorCode::Forbidden, __('That person may not unlock others.'), ['unlocked_by' => [__('That person may not unlock others.')]]);
            }
            $this->pins->unlock($person);
        }

        if ($data['outcome'] === 'locked_out_until_unlocked') {
            $this->pins->lock($person, $item->register->id);
        }

        AuditableEvent::dispatch(
            auditableType: User::class,
            auditableId: $person->id,
            auditableUuid: (string) $person->uuid,
            event: match ($data['outcome']) {
                'signed_in' => AuditEvent::LOGIN,
                'unlocked' => AuditEvent::UPDATED,
                default => AuditEvent::FAILED_LOGIN,
            },
            oldValues: null,
            newValues: [
                'till_sign_in' => $data['outcome'],
                'register' => (string) $item->register->uuid,
                'register_code' => $item->register->code,
                'recorded_at' => $item->recordedAt->toIso8601String(),
                'failed_attempts' => $data['failed_attempts'] ?? null,
                'lock_minutes' => $data['lock_minutes'] ?? null,
                'unlocked_by' => $data['outcome'] === 'unlocked' ? (string) $actor->uuid : null,
            ],
            status: in_array($data['outcome'], ['signed_in', 'unlocked'], true) ? AuditStatus::SUCCESS : AuditStatus::FAILED,
            userId: $actor->id,
            userName: $actor->name,
            userEmail: $actor->email,
            shopId: $item->register->shop_id,
            tags: ['till', 'till_pin', $data['outcome']],
            businessId: $item->register->shop?->business_id,
        );

        return ['flags' => []];
    }
}
