<?php

namespace App\Actions\Billing;

use App\Actions\Business\CopyRoleTemplates;
use App\Models\AiBlockedWork;
use App\Models\AiCreditBalance;
use App\Models\User;
use App\Notifications\AiCreditExhausted;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;

/**
 * Keeps AI work that could not run for want of credit (Phase 4, decision 4.9),
 * and tells the owners, once per empty spell (a top-up re-arms the notice).
 * The work runs again after the next top-up (RetryBlockedAiWork).
 */
class QueueBlockedAiWork
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(string $kind, array $payload, ?int $shopId, ?User $requester): AiBlockedWork
    {
        $work = AiBlockedWork::query()->create([
            'shop_id' => $shopId,
            'requested_by' => $requester?->id,
            'kind' => $kind,
            'payload' => $payload,
            'status' => AiBlockedWork::WAITING,
        ]);

        $this->notifyOwnersOnce();

        return $work;
    }

    private function notifyOwnersOnce(): void
    {
        $balance = AiCreditBalance::query()->firstOrCreate(['business_id' => $this->tenant->id()], ['balance' => 0]);

        if ($balance->exhausted_notified_at !== null) {
            return;
        }

        $owners = User::query()->inCurrentBusiness()->role(CopyRoleTemplates::OWNER_ROLE)->get();
        Notification::send($owners, new AiCreditExhausted($this->tenant->business()->name));

        $balance->update(['exhausted_notified_at' => now()]);
    }
}
