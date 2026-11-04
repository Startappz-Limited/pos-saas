<?php

namespace App\Support\Approvals;

use App\Models\RefundDue;
use App\Models\SaleVoidRequest;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Notifications\Approvals\ApprovalRequested;
use App\Notifications\Approvals\ApprovalSettled;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The notifications around an approval request (decided 3 November 2026,
 * stage 10). When it is made, every eligible approver except its creator is
 * notified; the first approval or rejection settles it, the others'
 * notifications are marked resolved, and the creator hears the outcome. All
 * queued, and sent only once the request's transaction commits.
 */
final class ApprovalInbox
{
    /**
     * The kinds of request, by the model holding them (status `pending` while
     * open).
     */
    private const KINDS = [
        'stock_adjustment' => StockAdjustment::class,
        'void_request' => SaleVoidRequest::class,
        'refund_due' => RefundDue::class,
    ];

    /**
     * @param  iterable<User>  $approvers
     */
    public function request(ApprovalSubject $subject, iterable $approvers, bool $reminder = false): void
    {
        $approvers = collect($approvers)->values();
        if ($approvers->isEmpty()) {
            return;
        }

        DB::afterCommit(fn () => Notification::send($approvers, new ApprovalRequested($subject, $reminder)));
    }

    /**
     * Marks every open notification about the request resolved, with how it
     * was settled, and tells its creator: approved or rejected (unless they
     * settled it themselves), or expired.
     */
    public function settle(ApprovalSubject $subject, ?User $creator, string $outcome, User $by, ?string $note = null): void
    {
        $this->resolve($subject->kind, $subject->uuid, $outcome);

        $told = $outcome === 'expired' || (in_array($outcome, ['approved', 'rejected'], true) && $creator?->id !== $by->id);
        if ($creator !== null && $told) {
            DB::afterCommit(fn () => $creator->notify(new ApprovalSettled($subject, $outcome, $by->name, $note)));
        }
    }

    public function resolve(string $kind, string $uuid, string $outcome): void
    {
        DatabaseNotification::query()
            ->where('type', ApprovalRequested::class)
            ->where('data->kind', $kind)
            ->where('data->subject_uuid', $uuid)
            ->get()
            ->each(function (DatabaseNotification $notification) use ($outcome): void {
                $notification->forceFill([
                    'data' => [...$notification->data, 'resolved' => $outcome],
                    'read_at' => $notification->read_at ?? now(),
                ])->save();
            });
    }

    public function isPending(string $kind, string $uuid): bool
    {
        $model = self::KINDS[$kind] ?? null;

        return $model !== null && $model::query()->where('uuid', $uuid)->value('status') === 'pending';
    }
}
