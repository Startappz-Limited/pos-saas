<?php

namespace App\Console\Concerns;

use App\Models\Business;
use App\Support\Tenancy\TenantContext;
use Symfony\Component\Console\Input\InputOption;

/**
 * A console command that works on one business's data (stage 1c, decision
 * 4.3): it takes `--business` (an id or uuid) and runs its body inside that
 * business, so the tenant scopes apply as they do for a signed-in request.
 *
 * Without the option it uses the business already set (when called from the
 * scheduler's per-business loop, or from tests), else the only business when
 * there is exactly one; with several it refuses rather than guess.
 *
 * The command implements handleForBusiness() instead of handle(); its
 * parameters are injected as handle()'s would be.
 */
trait RunsForBusiness
{
    protected function configure(): void
    {
        parent::configure();

        $this->addOption('business', null, InputOption::VALUE_REQUIRED, 'The business to act for (id or uuid); required when there are several');
    }

    public function handle(): int
    {
        $tenant = app(TenantContext::class);
        $business = $this->businessToActFor($tenant);

        if ($business === null) {
            return self::FAILURE;
        }

        return (int) $tenant->run($business, fn () => $this->laravel->call([$this, 'handleForBusiness']));
    }

    private function businessToActFor(TenantContext $tenant): ?Business
    {
        $named = $this->option('business');

        if ($named !== null && $named !== '') {
            $business = Business::query()
                ->when(ctype_digit((string) $named), fn ($query) => $query->whereKey((int) $named), fn ($query) => $query->where('uuid', $named))
                ->first();

            if ($business === null) {
                $this->error("No business {$named}.");
            }

            return $business;
        }

        if ($tenant->has()) {
            return $tenant->business();
        }

        $businesses = Business::query()->orderBy('id')->limit(2)->get();

        if ($businesses->count() === 1) {
            return $businesses->first();
        }

        $this->error($businesses->isEmpty()
            ? 'There is no business to act for.'
            : 'Several businesses exist: name one with --business=<id or uuid>.');

        return null;
    }
}
