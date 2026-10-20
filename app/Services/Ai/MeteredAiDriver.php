<?php

namespace App\Services\Ai;

use App\Enums\Feature;
use App\Exceptions\FeatureUnavailable;
use App\Exceptions\InsufficientAiCredit;
use App\Models\AiUsageEvent;
use App\Services\Ai\Contracts\AiContentGenerator;
use App\Services\Billing\AiCredits;
use App\Services\Billing\Entitlements;
use App\Support\Tenancy\TenantContext;
use Throwable;

/**
 * Meters every call of a real AI driver against the business's prepaid credit
 * (Phase 4, decision 4.6): the plan must include AI; the estimated cost is
 * reserved atomically before the call; the real cost (tokens, else the
 * per-call price) is settled after; a failed call is released; and every call
 * is recorded with its business, shop, provider, model, tokens and credits.
 *
 * A call at zero balance throws InsufficientAiCredit and the provider is never
 * reached, so usage can't go unrecorded.
 */
class MeteredAiDriver implements AiContentGenerator
{
    public function __construct(
        private AiContentGenerator $inner,
        private AiCredits $credits,
        private TenantContext $tenant,
        private Entitlements $entitlements,
        private ?int $shopId = null,
    ) {}

    public function name(): string
    {
        return $this->inner->name();
    }

    public function generateCampaignContent(array $context): array
    {
        if (! $this->entitlements->hasFeature(Feature::Ai)) {
            throw new FeatureUnavailable(Feature::Ai);
        }

        $businessId = (int) $this->tenant->id();
        $estimate = (float) config('ai.credits.per_call', 20);

        $this->credits->reserve($businessId, $estimate);

        try {
            $result = $this->inner->generateCampaignContent($context);
        } catch (Throwable $e) {
            $this->credits->release($businessId, $estimate);
            $this->record($businessId, 0.0, null, null, 'failed');

            throw $e;
        }

        $tokens = AiUsage::tokens($result['raw'] ?? null);
        $actual = $this->actualCost($tokens, $estimate);
        $charged = $this->credits->settle($businessId, $estimate, $actual);

        $this->record($businessId, $charged, $result['provider'] ?? $this->inner->name(), $result['model'] ?? null, 'settled', $tokens);

        return $result;
    }

    /**
     * @param  array{in: int|null, out: int|null}  $tokens
     */
    private function actualCost(array $tokens, float $perCall): float
    {
        if ($tokens['in'] === null && $tokens['out'] === null) {
            return $perCall;
        }

        return round((($tokens['in'] ?? 0) + ($tokens['out'] ?? 0)) / 1000 * (float) config('ai.credits.per_1k_tokens', 10), 4);
    }

    /**
     * @param  array{in: int|null, out: int|null}|null  $tokens
     */
    private function record(int $businessId, float $credits, ?string $provider, ?string $model, string $status, ?array $tokens = null): void
    {
        AiUsageEvent::query()->create([
            'business_id' => $businessId,
            'shop_id' => $this->shopId,
            'provider' => $provider ?? $this->inner->name(),
            'model' => $model,
            'tokens_in' => $tokens['in'] ?? null,
            'tokens_out' => $tokens['out'] ?? null,
            'credits' => $credits,
            'kind' => 'call',
            'status' => $status,
            'user_id' => auth()->id(),
        ]);
    }
}
