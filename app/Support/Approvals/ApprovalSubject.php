<?php

namespace App\Support\Approvals;

/**
 * What an approval notification is about, as plain values so it survives the
 * queue: its kind (`stock_adjustment`, `void_request`), uuid, business, a title
 * and summary, the page to open, and who asked.
 */
final class ApprovalSubject
{
    public function __construct(
        public readonly string $kind,
        public readonly string $uuid,
        public readonly int $businessId,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $url,
        public readonly string $requestedBy,
        // What the notification asks for; "Approval needed" when null.
        public readonly ?string $prompt = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'subject_uuid' => $this->uuid,
            'business_id' => $this->businessId,
            'title' => $this->title,
            'summary' => $this->summary,
            'url' => $this->url,
            'requested_by' => $this->requestedBy,
            'prompt' => $this->prompt,
        ];
    }
}
