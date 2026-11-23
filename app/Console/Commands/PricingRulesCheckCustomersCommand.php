<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Models\Business;
use App\Models\Customer;
use App\Models\PricingRule;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Lists the pricing rules whose customer isn't a customer of the business (stage 11b, step 2). Before the re-point
 * `pricing_rules.customer_id` named staff users, so a rule may name a person who isn't a customer. Read-only: nothing
 * is changed. The migration that re-points the column refuses while any such rule exists.
 */
class PricingRulesCheckCustomersCommand extends Command
{
    use RunsForBusiness;

    protected $signature = 'pricing-rules:check-customers';

    protected $description = 'List pricing rules whose customer is not a customer of the business (read-only)';

    public function handleForBusiness(TenantContext $tenant): int
    {
        $business = $tenant->business();
        if (! $business instanceof Business) {
            $this->error('No business is set.');

            return self::FAILURE;
        }

        $rules = PricingRule::query()->whereNotNull('customer_id')->get(['id', 'uuid', 'name', 'customer_id']);
        $customerIds = Customer::query()->pluck('id')->flip();

        $misnamed = $rules->reject(fn (PricingRule $rule): bool => $customerIds->has($rule->customer_id));

        if ($misnamed->isEmpty()) {
            $this->info('Every customer-specific rule names a customer.');

            return self::SUCCESS;
        }

        $this->table(['id', 'uuid', 'name', 'names user id'], $misnamed->map(fn (PricingRule $rule): array => [
            $rule->id, $rule->uuid, $rule->name, $rule->customer_id,
        ])->all());
        $this->warn($misnamed->count().' rule(s) name someone who is not a customer of this business. Fix them before the re-point migration runs. Nothing was changed.');

        return self::FAILURE;
    }
}
