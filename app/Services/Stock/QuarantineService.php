<?php

namespace App\Services\Stock;

use App\Casts\Quantity;
use App\Enums\QuarantineStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\PushShopStockJob;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockRelease;
use App\Models\User;
use App\Support\Stock\Expiry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Verifies and releases quarantined stock (stage 11, quarantine policy). Quarantined stock is on hand but never
 * sold. It leaves quarantine only by an approved release, which moves a quantity to a new sellable layer that keeps
 * the original batch and expiry, and records the approval separately (a StockRelease). Medical stock that is known
 * to be expired, or whose expiry is unverified, cannot be released; known expired medical stock leaves through a
 * disposal or supplier return instead.
 *
 * Every write runs under a lock on the source layer, so concurrent requests serialise. A retried release (the same
 * request key in the same shop) returns the release it already made and changes nothing.
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
     * Releases a quantity of a quarantined layer for sale. Returns the release, and whether it was already made
     * (a retry). Only the first call with a request key moves stock.
     */
    public function release(CostLayer $layer, User $user, string $quantity, string $reason, string $requestKey): array
    {
        $this->authorize($user);

        $quantity = Quantity::normalize($quantity);
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new BusinessRuleException(__('The quantity to release must be more than zero.'));
        }

        [$release, $replayed] = DB::transaction(function () use ($layer, $user, $quantity, $reason, $requestKey): array {
            $source = CostLayer::query()->whereKey($layer->id)->lockForUpdate()->firstOrFail();

            // A retry of a release already made in this shop is answered with that release, and moves nothing.
            $existing = StockRelease::query()->where('shop_id', $source->shop_id)->where('request_key', $requestKey)->first();
            if ($existing !== null) {
                return [$existing, true];
            }

            $this->assertReleasable($source, $quantity);

            $remaining = Quantity::normalize($source->remaining_quantity);
            $source->update(['remaining_quantity' => bcsub($remaining, $quantity, 3), 'consumed_at' => bccomp($remaining, $quantity, 3) === 0 ? now() : null]);

            // The released stock is a new sellable layer: same batch, expiry and cost as the quarantined layer it came from.
            $released = CostLayer::query()->create([
                'uuid' => (string) Str::uuid(),
                'business_id' => $source->business_id,
                'shop_id' => $source->shop_id,
                'product_id' => $source->product_id,
                'variation_id' => $source->variation_id,
                'source_type' => CostLayer::SOURCE_QUARANTINE_RELEASE,
                'source_id' => $source->id,
                'unit_cost' => $source->unit_cost,
                'batch_number' => $source->batch_number,
                'expiry_date' => $source->expiry_date,
                'original_quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'received_at' => $source->received_at,
            ]);

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

            return [$release, false];
        });

        // Sellable stock grew, so the stores and tills must hear about it. Dispatched after the commit, never inside it.
        if (! $replayed) {
            PushShopStockJob::dispatch((int) $release->shop_id, (int) $release->product_id);
        }

        return [$release, $replayed];
    }

    /**
     * Why a quarantined layer cannot be released at all, or null when it can (quantity aside). The inspection screen
     * and the release itself both use this, so they never disagree.
     */
    public function blockedReason(CostLayer $layer): ?string
    {
        if (! $layer->isQuarantined()) {
            return __('This stock is not in quarantine.');
        }

        $medical = (bool) Product::query()->whereKey($layer->product_id)->value('is_medical');

        if ($medical && $layer->quarantine_status === QuarantineStatus::EXPIRED) {
            return __('Expired medical stock cannot be released for sale. Dispose of it or return it to the supplier.');
        }

        if ($medical && $layer->quarantine_status === QuarantineStatus::UNVERIFIED) {
            return __('Medical stock must have its batch and expiry verified before it can be released.');
        }

        return null;
    }

    private function assertReleasable(CostLayer $source, string $quantity): void
    {
        if (($reason = $this->blockedReason($source)) !== null) {
            throw new BusinessRuleException($reason);
        }

        $remaining = Quantity::normalize($source->remaining_quantity);
        if (bccomp($quantity, $remaining, 3) > 0) {
            throw new BusinessRuleException(__('Only :available left in quarantine; :needed requested.', ['available' => $remaining, 'needed' => $quantity]));
        }
    }

    private function authorize(User $user): void
    {
        if (! $user->can(self::PERMISSION)) {
            throw new AuthorizationException(__('You are not allowed to release quarantined stock.'));
        }
    }
}
