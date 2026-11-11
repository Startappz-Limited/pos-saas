<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Enums\BusinessType;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;

/**
 * Gives a business with no type yet one (stage 11, step 5): a UAT business
 * predates the field. Never touches settings — the type sets only what a
 * new product or sign-up takes afterwards. Dry run by default; refuses an
 * unknown type.
 */
class SetBusinessType extends Command
{
    use RunsForBusiness;

    protected $signature = 'businesses:set-type
        {--type= : The business type to set (see App\\Enums\\BusinessType)}
        {--apply : Apply the change (without it, only report)}';

    protected $description = 'Give a business with no type yet one (dry run unless --apply).';

    protected function supportsAllBusinesses(): bool
    {
        return true;
    }

    public function handleForBusiness(TenantContext $tenant): int
    {
        $type = BusinessType::tryFrom((string) $this->option('type'));

        if ($type === null) {
            $this->error('Give a valid --type: '.implode(', ', array_column(BusinessType::cases(), 'value')));

            return self::FAILURE;
        }

        $business = $tenant->business();

        if ($business->type !== null) {
            $this->info("{$business->name}: already has a type ({$business->type->value}).");

            return self::SUCCESS;
        }

        $this->info("{$business->name}: {$type->value}".($this->option('apply') ? '' : ' (dry run)'));

        if ($this->option('apply')) {
            $business->update(['type' => $type]);
        }

        return self::SUCCESS;
    }
}
