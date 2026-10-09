<?php

namespace App\Actions\PricingRule;

use App\Models\PricingRule;

class DeletePricingRuleAction
{
    public function execute(PricingRule $pricingRule): bool
    {
        return $pricingRule->delete();
    }
}
