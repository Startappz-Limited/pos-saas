<?php

namespace App\Services\Stock;

use App\Casts\Quantity;
use App\Enums\QuarantineStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\PushShopStockJob;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Shop;
use App\Models\StockRelease;
use App\Models\User;
use App\Rules\QuantityForUnit;
use App\Support\Stock\Expiry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Verifies and releases quarantined stock (stage 11, quarantine policy). Quarantined stock is on hand but never
 * sold. It leaves quarantine only by an approved release, which moves a quantity to a new sellable layer that keeps
 * the original batch and expiry, and records the approval separately (a StockRelease).
 *
 * A release locks the variation's stock row first, then the source layer: the order a sale takes, so the two
 * serialise. Provisional sales waiting for stock are settled in the same transaction. A retried release (the same
 * request key) answers with the release it made; a key reused for a different release is refused.
 */
class QuarantineService
{
    public const PERMISSION = 'stock.release-quarantine';

    /**
     * Records the batch and expiry of an unverified layer. The expiry decides the next status: a past date is
     * known expired, a future one is held for release.
     */
    public function verify(CostLayer $layer, User $user, string $batch, string $expiry): CostLayer
    {
        $this->authorize($user);

        return DB::transaction(function () use ($layer, $user, $batch, $expiry): CostLayer {
            $probe = CostLayer::query()->whereKey($layer->id)->firstOrFail();
            $this->ledger()->lockVariationStock($probe->shop_id, $this->variationOf($probe));

            $source = CostLayer::query()->whereKey($layer->id)->lockForUpdate()->firstOrFail();

            if ($source->quarantine_status !== QuarantineStatus::UNVERIFIED) {
                throw new BusinessRuleException(__('Only stock whose batch or expiry is unverified needs verifying.'));
            }

            $expiryDate = Carbon::parse($expiry)->toDateString();
            $boundary = Expiry::firstSellableDate(Shop::query()->findOrFail($source->shop_id))->toDateString();

            $source->update([
                'batch_number' => $batch,
                'expiry_date' => $expiryDate,
                'quarantine_status' => $expiryDate < $boundary ? QuarantineStatus::EXPIRED : QuarantineStatus::HELD,
                'quarantine_reason' => __('Verified by :user on :date.', ['user' => $user->name, 'date' => now()->toDateString()]),
                'quarantine_verified_at' => now(),
                'quarantine_verified_by' => $user->id,
            ]);

            return $source->fresh();
        });
    }

    /**
     * Releases a quantity of a quarantined layer for sale. Returns [release, replayed]: replayed is true when the same
     * request was already made, in which case nothing moves.
     *
     * @return array{0: StockRelease, 1: bool}
     */
    public function release(CostLayer $layer, User $user, string $quantity, string $reason, string $requestKey): array
    {
        $this->authorize($user);

        $quantity = Quantity::normalize($quantity);
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new BusinessRuleException(__('The quantity to release must be more than zero.'));
        }

        $probe = CostLayer::query()->whereKey($layer->id)->firstOrFail();

        try {
            [$release, $replayed] = DB::transaction(function () use ($probe, $user, $quantity, $reason, $requestKey): array {
                $this->ledger()->lockVariationStock($probe->shop_id, $this->variationOf($probe));

                $source = CostLayer::query()->whereKey($probe->id)->lockForUpdate()->firstOrFail();

                $existing = StockRelease::query()->where('shop_id', $source->shop_id)->where('request_key', $requestKey)->first();
                if ($existing !== null) {
                    $this->assertSameRelease($existing, $source, $quantity, $reason);

                    return [$existing, true];
                }

                $this->assertReleasable($source, $quantity);

                $released = $this->ledger()->addQuarantineRelease($source, $quantity);

                $release = StockRelease::query()->create([
                    'uuid' => (string) Str::uuid(),
                    'business_id' => $source->business_id,
                    'shop_id' => $source->shop_id,
                    'product_id' => $source->product_id,
                    'variation_id' => $source->variation_id,
                    'from_cost_layer_id' => $source->id,
                    'to_cost_layer_id' => $released->id,
                    'quantity' => $quantity,
                    'quarantine_status' => $source->quarantine_status,
                    'batch_number' => $source->batch_number,
                    'expiry_date' => $source->expiry_date,
                    'reason' => $reason,
                    'request_key' => $requestKey,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);

                $released->update(['stock_release_id' => $release->id]);
                $this->ledger()->settleReleased($released);

                return [$release, false];
            });
        } catch (QueryException $e) {
            // Two requests with the same key, against different layers, can both pass the lock above: the second
            // insert hits the unique key. Its winner is then loaded and compared, as a retry would be.
            $existing = StockRelease::query()->where('shop_id', $probe->shop_id)->where('request_key', $requestKey)->first();
            if ($existing === null) {
                throw $e;
            }

            $this->assertSameRelease($existing, $probe, $quantity, $reason);
            [$release, $replayed] = [$existing, true];
        }

        // Sellable stock grew, so the stores and tills must hear about it. Dispatched after the commit, never inside it.
        if (! $replayed) {
            PushShopStockJob::dispatch((int) $release->shop_id, (int) $release->product_id);
        }

        return [$release, $replayed];
    }

    /**
     * Why a quarantined layer cannot be released at all, or null when it can (quantity aside). Judged now, not at the
     * time it was verified: medical stock that has expired since is refused, however it was stored.
     */
    public function blockedReason(CostLayer $layer): ?string
    {
        if (! $layer->isQuarantined()) {
            return __('This stock is not in quarantine.');
        }

        $medical = (bool) Product::query()->whereKey($layer->product_id)->value('is_medical');
        if (! $medical) {
            return null;
        }

        if ($layer->quarantine_status === QuarantineStatus::UNVERIFIED || $layer->expiry_date === null) {
            return __('Medical stock must have its batch and expiry verified before it can be released.');
        }

        $boundary = Expiry::firstSellableDate(Shop::query()->findOrFail($layer->shop_id))->toDateString();

        if ($layer->quarantine_status === QuarantineStatus::EXPIRED || $layer->expiry_date->toDateString() < $boundary) {
            return __('Expired medical stock cannot be released for sale. Dispose of it or return it to the supplier.');
        }

        return null;
    }

    /**
     * The decimal places a layer's quantities may use: its variation's base unit (a whole-unit product takes none).
     */
    public function baseUnitPrecision(CostLayer $layer): int
    {
        return (int) (ProductVariation::withoutGlobalScope(ProductVariation::HIDE_DEFAULT)->find($layer->variation_id)?->baseUnit?->precision ?? 3);
    }

    private function assertReleasable(CostLayer $source, string $quantity): void
    {
        if (($reason = $this->blockedReason($source)) !== null) {
            throw new BusinessRuleException($reason);
        }

        (new QuantityForUnit($this->baseUnitPrecision($source)))->validate('quantity', $quantity, function (string $message): void {
            throw new BusinessRuleException($message);
        });

        $remaining = Quantity::normalize($source->remaining_quantity);
        if (bccomp($quantity, $remaining, 3) > 0) {
            throw new BusinessRuleException(__('Only :available left in quarantine; :needed requested.', ['available' => $remaining, 'needed' => $quantity]));
        }
    }

    /**
     * A reused request key is a retry only when it asks for the same release. Anything else is refused, so a key can
     * never report another operation as complete.
     */
    private function assertSameRelease(StockRelease $existing, CostLayer $source, string $quantity, string $reason): void
    {
        if ((int) $existing->from_cost_layer_id !== (int) $source->id
            || bccomp((string) $existing->quantity, $quantity, 3) !== 0
            || $existing->reason !== $reason) {
            throw new BusinessRuleException(__('This request key was already used for a different release.'));
        }
    }

    private function variationOf(CostLayer $layer): ProductVariation
    {
        return ProductVariation::withoutGlobalScope(ProductVariation::HIDE_DEFAULT)->findOrFail($layer->variation_id);
    }

    private function ledger(): StockLedger
    {
        return app(StockLedger::class);
    }

    private function authorize(User $user): void
    {
        if (! $user->can(self::PERMISSION)) {
            throw new AuthorizationException(__('You are not allowed to release quarantined stock.'));
        }
    }
}
