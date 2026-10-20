<?php

namespace App\Services\Billing;

/**
 * What the M-Pesa API answered when asked about a checkout request.
 */
final readonly class CollectionResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $paid,
        public ?string $receipt = null,
        public ?float $amount = null,
        public ?string $reference = null,
        public array $raw = [],
        public ?string $error = null,
    ) {}
}
