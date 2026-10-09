<?php

namespace App\Actions\PricingRule;

use App\Models\PricingRule;

class UpdatePricingRuleAction
{
    public function execute(PricingRule $pricingRule, array $data): PricingRule
    {
        $data['updated_by'] = auth()->id();

        $pricingRule->update($data);

        return $pricingRule->fresh();
    }
}
