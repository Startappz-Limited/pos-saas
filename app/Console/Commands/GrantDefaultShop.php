<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * On the Free Trial, gives every non-owner member without a shop the
 * business's default shop, as the Free Trial rule says (stage 10a, step
 * 3). A business on any other tier is only listed, for its owner to assign
 * shops by hand. Dry run by default.
 */
class GrantDefaultShop extends Command
{
    use RunsForBusiness;

    protected $signature = 'users:grant-default-shop
        {--apply : Apply the change (without it, only report)}';

    protected $description = 'Give Free Trial businesses\' shopless members the default shop; list others for their owner (dry run unless --apply).';

    protected function supportsAllBusinesses(): bool
    {
        return true;
    }

    public function handleForBusiness(TenantContext $tenant): int
    {
        $business = $tenant->business();

        $without = User::query()->inCurrentBusiness()->get()
            ->reject(fn (User $user): bool => $user->isBusinessOwner())
            ->reject(fn (User $user): bool => $user->hasAnyShop());

        if ($without->isEmpty()) {
            $this->info('Every non-owner member has a shop.');

            return self::SUCCESS;
        }

        if (! $business->onFreeTrial()) {
            $this->warn('Not on the Free Trial: listed for the owner to assign shops by hand.');
            $this->table(['Name', 'Email'], $without->map(fn (User $user): array => [$user->name, $user->email])->all());

            return self::SUCCESS;
        }

        if ($business->default_shop_id === null) {
            $this->error('No default shop set yet: run businesses:set-default-shop first.');

            return self::FAILURE;
        }

        $this->table(['Name', 'Email'], $without->map(fn (User $user): array => [$user->name, $user->email])->all());

        if ($this->option('apply')) {
            foreach ($without as $user) {
                $user->shops()->syncWithoutDetaching([$business->default_shop_id]);
            }
            $this->info('Given the default shop.');
        } else {
            $this->warn('Dry run: nothing changed. Run again with --apply.');
        }

        return self::SUCCESS;
    }
}
