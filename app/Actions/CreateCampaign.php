<?php

namespace App\Actions;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Support\Arr;

class CreateCampaign
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Campaign
    {
        $data['status'] = $data['status'] ?? CampaignStatus::DRAFT->value;
        $data['currency'] = $data['currency'] ?? 'KES';

        $productIds = Arr::pull($data, 'product_ids', []);

        $campaign = Campaign::create($data);

        if (! empty($productIds)) {
            $campaign->products()->sync(
                collect($productIds)
                    ->mapWithKeys(fn($id, $i) => [$id => ['display_order' => $i]])
                    ->all()
            );
        }

        return $campaign->fresh(['shop', 'products']);
    }
}
