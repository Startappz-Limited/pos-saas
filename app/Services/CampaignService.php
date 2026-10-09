<?php

namespace App\Services;

use App\Actions\CreateCampaign;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CampaignService
{
    public function __construct(protected CreateCampaign $createCampaign) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Campaign::query()->with(['shop', 'creator']);

        $this->applyFilters($query, $filters);

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * @return array<string, int>
     */
    public function getStatistics(?int $shopId = null): array
    {
        $base = Campaign::query()->when($shopId, fn($q) => $q->forShop($shopId));

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('status', CampaignStatus::ACTIVE)->count(),
            'scheduled' => (clone $base)->where('status', CampaignStatus::SCHEDULED)->count(),
            'completed' => (clone $base)->where('status', CampaignStatus::COMPLETED)->count(),
            'draft' => (clone $base)->where('status', CampaignStatus::DRAFT)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Campaign
    {
        return $this->createCampaign->handle($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        $productIds = $data['product_ids'] ?? null;
        unset($data['product_ids']);

        $campaign->update($data);

        if (is_array($productIds)) {
            $campaign->products()->sync(
                collect($productIds)
                    ->mapWithKeys(fn($id, $i) => [$id => ['display_order' => $i]])
                    ->all()
            );
        }

        return $campaign->fresh(['shop', 'products']);
    }

    public function delete(Campaign $campaign): bool
    {
        return (bool) $campaign->delete();
    }

    public function start(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => CampaignStatus::ACTIVE]);

        return $campaign->fresh();
    }

    public function pause(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => CampaignStatus::PAUSED]);

        return $campaign->fresh();
    }

    public function complete(Campaign $campaign): Campaign
    {
        $campaign->update(['status' => CampaignStatus::COMPLETED]);

        return $campaign->fresh();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('promo_code', 'like', $term);
            });
        }

        if (! empty($filters['shop_id'])) {
            $query->where('shop_id', $filters['shop_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['campaign_type'])) {
            $query->where('campaign_type', $filters['campaign_type']);
        }
    }
}
