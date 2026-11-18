<?php

namespace App\Support\Stock;

use App\Enums\QuarantineStatus;

/**
 * Which returned stock is quarantined, and why (stage 11, quarantine policy). Stock is quarantined when its
 * batch or its expiry cannot be established, and when it is known to be expired: an expired medical product's
 * stock stays quarantined, and any other expired returned stock waits for an approved release. Stock that is
 * known and not expired is not quarantined, so it sells as before.
 *
 * Expiry is judged against the shop's first sellable day ({@see Expiry}), the same boundary the sale path uses.
 */
final class QuarantinePolicy
{
    /**
     * @return array{quantity: string, batch_number: ?string, expiry_date: ?string, quarantine: ?QuarantineStatus, reason: ?string}
     */
    public static function classify(
        string $quantity,
        ?string $batch,
        ?string $expiry,
        bool $tracksBatches,
        bool $medical,
        string $boundary,
        string $returnNumber,
    ): array {
        // A product that tracks batches must name one, and so must any expiry-bearing (medical or batch-tracked)
        // stock: without an expiry it cannot be judged safe.
        $unknownBatch = $tracksBatches && blank($batch);
        $unknownExpiry = $expiry === null && ($medical || $tracksBatches);

        [$status, $reason] = match (true) {
            $unknownBatch => [QuarantineStatus::UNVERIFIED, "Return {$returnNumber}: the batch could not be established."],
            $unknownExpiry => [QuarantineStatus::UNVERIFIED, "Return {$returnNumber}: the expiry could not be established."],
            $expiry !== null && $expiry < $boundary => [QuarantineStatus::EXPIRED, "Return {$returnNumber}: returned expired (batch {$batch})."],
            default => [null, null],
        };

        return [
            'quantity' => $quantity,
            'batch_number' => $batch,
            'expiry_date' => $expiry,
            'quarantine' => $status,
            'reason' => $reason,
        ];
    }
}
