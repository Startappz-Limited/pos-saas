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
 * A command that overrides `supportsAllBusinesses()` to true also takes
 * `--all` (stage 10a, step 1), running `handleForBusiness()` once per
 * business with a header line between them, in two flavours: every *active*
 * business (`TenantContext::eachBusiness()`, the default) or, for a command
 * that overrides `allBusinessesEveryStatus()` to true as well, every
 * business whatever its status — needed by a command that repairs rows of a
 * business that is now suspended, closing or unverified.
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

        if ($this->supportsAllBusinesses()) {
            $this->addOption('all', null, InputOption::VALUE_NONE, $this->allBusinessesEveryStatus()
                ? 'Run for every business, whatever its status'
                : 'Run for every active business');
        }
    }

    public function handle(): int
    {
        $tenant = app(TenantContext::class);

        if ($this->supportsAllBusinesses() && $this->option('all')) {
            if ($this->option('business')) {
                $this->error('Pass --business or --all, not both.');

                return self::FAILURE;
            }

            return $this->handleAllBusinesses($tenant);
        }

        $business = $this->businessToActFor($tenant);

        if ($business === null) {
            return self::FAILURE;
        }

        return (int) $tenant->run($business, fn () => $this->laravel->call([$this, 'handleForBusiness']));
    }

    /**
     * Override to true to offer `--all` on this command.
     */
    protected function supportsAllBusinesses(): bool
    {
        return false;
    }

    /**
     * Override to true so `--all` reaches every business whatever its
     * status, not only the active ones `eachBusiness()` covers.
     */
    protected function allBusinessesEveryStatus(): bool
    {
        return false;
    }

    private function handleAllBusinesses(TenantContext $tenant): int
    {
        $failed = false;

        $run = function (Business $business) use ($tenant, &$failed): void {
            $this->line("=== {$business->name} (#{$business->id}) ===");

            if ((int) $tenant->run($business, fn () => $this->laravel->call([$this, 'handleForBusiness'])) !== self::SUCCESS) {
                $failed = true;
            }
        };

        if ($this->allBusinessesEveryStatus()) {
            foreach (Business::query()->orderBy('id')->cursor() as $business) {
                $run($business);
            }
        } else {
            $tenant->eachBusiness($run);
        }

        return $failed ? self::FAILURE : self::SUCCESS;
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
