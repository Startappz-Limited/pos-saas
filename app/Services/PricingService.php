<?php

namespace App\Services;

use App\Actions\PricingRule\CreatePricingRuleAction;
use App\Actions\PricingRule\DeletePricingRuleAction;
use App\Actions\PricingRule\UpdatePricingRuleAction;
use App\Enums\PricingType;
use App\Models\PricingRule;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PricingService
{
    public function __construct(
        protected CreatePricingRuleAction $createAction,
        protected UpdatePricingRuleAction $updateAction,
        protected DeletePricingRuleAction $deleteAction
    ) {}

    public function getAllPricingRules(
        int $perPage = 15,
        ?string $search = null,
        ?PricingType $type = null,
        ?int $productId = null,
        ?bool $isActive = null
    ): LengthAwarePaginator {
        $query = PricingRule::with(['product', 'customer', 'creator'])
            ->orderByPriority();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($type) {
            $query->byType($type);
        }

        if ($productId) {
            $query->forProduct($productId);
        }

        if ($isActive !== null) {
            $isActive ? $query->active() : $query->inactive();
        }

        return $query->paginate($perPage);
    }

    public function getActivePricingRules(?int $productId = null): Collection
    {
        $query = PricingRule::with(['product', 'customer'])
            ->currentlyValid()
            ->orderByPriority();

        if ($productId) {
            $query->forProduct($productId);
        }

        return $query->get();
    }

    public function getExpiredPricingRules(): Collection
    {
        return PricingRule::where('is_active', true)
            ->whereNotNull('end_date')
            ->where('end_date', '<', now())
            ->with(['product'])
            ->orderBy('end_date', 'desc')
            ->get();
    }

    public function getUpcomingPricingRules(): Collection
    {
        return PricingRule::where('is_active', true)
            ->whereNotNull('start_date')
            ->where('start_date', '>', now())
            ->with(['product'])
            ->orderBy('start_date', 'asc')
            ->get();
    }

    public function createPricingRule(array $data): PricingRule
    {
        return $this->createAction->execute($data);
    }

    public function updatePricingRule(PricingRule $pricingRule, array $data): PricingRule
    {
        return $this->updateAction->execute($pricingRule, $data);
    }

    public function deletePricingRule(PricingRule $pricingRule): bool
    {
        return $this->deleteAction->execute($pricingRule);
    }

    public function activatePricingRule(PricingRule $pricingRule): PricingRule
    {
        return $this->updatePricingRule($pricingRule, ['is_active' => true]);
    }

    public function deactivatePricingRule(PricingRule $pricingRule): PricingRule
    {
        return $this->updatePricingRule($pricingRule, ['is_active' => false]);
    }

    public function getBestPriceForProduct(
        Product $product,
        int $quantity = 1,
        ?int $customerId = null
    ): ?array {
        $rules = PricingRule::forProduct($product->id)
            ->currentlyValid()
            ->forQuantity($quantity)
            ->orderByPriority()
            ->get();

        $bestPrice = null;
        $bestRule = null;

        foreach ($rules as $rule) {
            if (! $rule->isValidForCustomer($customerId)) {
                continue;
            }

            $finalPrice = $rule->calculateFinalPrice($quantity);

            if ($bestPrice === null || $finalPrice < $bestPrice) {
                $bestPrice = $finalPrice;
                $bestRule = $rule;
            }
        }

        // Fallback to product's default selling price
        if ($bestPrice === null) {
            $bestPrice = (float) $product->selling_price * $quantity;
        }

        return [
            'price' => $bestPrice,
            'unit_price' => $bestPrice / $quantity,
            'quantity' => $quantity,
            'rule' => $bestRule,
            'has_discount' => $bestRule !== null,
            'original_price' => (float) $product->selling_price * $quantity,
            'savings' => $bestRule ? ((float) $product->selling_price * $quantity) - $bestPrice : 0,
        ];
    }

    public function getStatistics(): array
    {
        return [
            'total' => PricingRule::count(),
            'active' => PricingRule::active()->count(),
            'inactive' => PricingRule::inactive()->count(),
            'expired' => PricingRule::where('is_active', true)
                ->whereNotNull('end_date')
                ->where('end_date', '<', now())
                ->count(),
            'upcoming' => PricingRule::where('is_active', true)
                ->whereNotNull('start_date')
                ->where('start_date', '>', now())
                ->count(),
            'by_type' => PricingRule::selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->get()
                ->pluck('count', 'type')
                ->toArray(),
        ];
    }
}
