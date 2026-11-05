<?php

namespace App\Support\Approvals;

use App\Enums\AlertCategory;
use App\Enums\AlertType;
use App\Models\Alert;
use Illuminate\Database\Eloquent\Model;

/**
 * Something to check by hand (stage 10, steps 5b and 5c): an online order
 * cancelled after dispatch or unrefunded, a session corrected by a late void.
 * An `alerts` row on the record (`sale_review`) for the shop's staff, shown
 * until resolved, once per record and reason; and, where decided, the owners
 * notified under the shared rule.
 */
final class Flags
{
    public function __construct(private ApprovalInbox $inbox, private Approvers $approvers) {}

    public function raise(Model $subject, int $shopId, int $businessId, string $code, string $title, string $message, string $url, bool $notifyOwners): ?Alert
    {
        $exists = Alert::query()->where('alertable_type', $subject::class)->where('alertable_id', $subject->getKey())
            ->where('type', AlertType::SALE_REVIEW->value)->where('data->code', $code)->exists();
        if ($exists) {
            return null;
        }

        $alert = Alert::create([
            'shop_id' => $shopId,
            'title' => $title,
            'message' => $message,
            'type' => AlertType::SALE_REVIEW,
            'severity' => AlertType::SALE_REVIEW->defaultSeverity(),
            'category' => AlertCategory::SALES,
            'alertable_type' => $subject::class,
            'alertable_id' => $subject->getKey(),
            'data' => ['code' => $code],
        ]);

        if ($notifyOwners) {
            $this->inbox->request(new ApprovalSubject(
                kind: 'sale_review',
                uuid: (string) $alert->uuid,
                businessId: $businessId,
                title: $title,
                summary: $message,
                url: $url,
                requestedBy: __('the system'),
                prompt: __('Check'),
            ), $this->approvers->owners());
        }

        return $alert;
    }
}
