<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Support\Business\DefaultShopAudit;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Sets the default shop of a business that doesn't have one yet (stage 10a,
 * step 3), to its oldest active shop. Dry run by default. Covers every
 * business whatever its status, with `--all`, so one reopened or verified
 * later already has its default shop.
 */
class SetDefaultShop extends Command
{
    use RunsForBusiness;

    protected $signature = 'businesses:set-default-shop
        {--apply : Apply the change (without it, only report)}';

    protected $description = 'Set the oldest active shop as a business\'s default shop, where it has none (dry run unless --apply).';

    protected function supportsAllBusinesses(): bool
    {
        return true;
    }

    protected function allBusinessesEveryStatus(): bool
    {
        return true;
    }

    public function handleForBusiness(TenantContext $tenant): int
    {
        $business = $tenant->business();

        if ($business->default_shop_id !== null) {
            $this->info('Already has a default shop.');

            return self::SUCCESS;
        }

        $oldest = Shop::query()->where('status', ShopStatus::ACTIVE)->oldest('id')->first();

        if ($oldest === null) {
            $this->warn('No active shop to set as default.');

            return self::SUCCESS;
        }

        $this->info("Default shop: {$oldest->name} (#{$oldest->id})".($this->option('apply') ? '' : ' (dry run)'));

        if ($this->option('apply')) {
            $business->forceFill(['default_shop_id' => $oldest->id])->save();
            DefaultShopAudit::record($business, null, $oldest->id);
        }

        return self::SUCCESS;
    }
}
