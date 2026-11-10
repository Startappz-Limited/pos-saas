<?php

namespace App\Console\Commands;

use App\Console\Concerns\RunsForBusiness;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Lists every non-owner member of a business with no shop assigned (stage
 * 10a, step 3). Read-only.
 */
class ListUsersWithoutShop extends Command
{
    use RunsForBusiness;

    protected $signature = 'users:list-without-shop';

    protected $description = 'List every non-owner member of a business with no shop assigned (read-only).';

    protected function supportsAllBusinesses(): bool
    {
        return true;
    }

    public function handleForBusiness(): int
    {
        $without = User::query()->inCurrentBusiness()->get()
            ->reject(fn (User $user): bool => $user->isBusinessOwner())
            ->reject(fn (User $user): bool => $user->hasAnyShop());

        if ($without->isEmpty()) {
            $this->info('Every non-owner member has a shop.');

            return self::SUCCESS;
        }

        $this->table(['Name', 'Email'], $without->map(fn (User $user): array => [$user->name, $user->email])->all());

        return self::SUCCESS;
    }
}
