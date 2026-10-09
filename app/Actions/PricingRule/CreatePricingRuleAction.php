<?php

namespace App\Actions\PricingRule;

use App\Models\PricingRule;
use Illuminate\Support\Str;

class CreatePricingRuleAction
{
    public function execute(array $data): PricingRule
    {
        if (empty($data['uuid'])) {
            $data['uuid'] = (string) Str::uuid();
        }

        if (! isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        if (! isset($data['priority'])) {
            $data['priority'] = 0;
        }

        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return PricingRule::create($data);
    }
}
