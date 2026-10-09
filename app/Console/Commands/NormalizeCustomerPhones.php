<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;

/**
 * Backfills `customers.phone_normalized` for rows created before the column existed.
 *
 * New and updated customers get the canonical MSISDN written automatically by the
 * model. Historic rows have nothing in the column, so any lookup keyed on it —
 * duplicate detection, WhatsApp delivery, matching a mobile-money payer — misses
 * them entirely until this has run.
 *
 * `phone` itself is never touched: it keeps whatever the cashier typed.
 */
class NormalizeCustomerPhones extends Command
{
    protected $signature = 'customers:normalize-phones
                            {--apply : Write the changes (defaults to a dry run)}
                            {--show-duplicates : List numbers held by more than one customer}';

    protected $description = 'Backfill the canonical phone number on customers from their entered phone';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $updated = 0;
        $unchanged = 0;
        $unparseable = [];
        $seen = [];

        Customer::query()
            ->withTrashed()
            ->select(['id', 'name', 'phone', 'phone_normalized'])
            ->chunkById(500, function ($customers) use ($apply, &$updated, &$unchanged, &$unparseable, &$seen): void {
                foreach ($customers as $customer) {
                    $normalized = PhoneNumber::normalize($customer->phone);

                    if ($normalized === null) {
                        if ($customer->phone !== null && trim((string) $customer->phone) !== '') {
                            $unparseable[] = [$customer->id, $customer->name, $customer->phone];
                        }

                        $unchanged++;

                        continue;
                    }

                    $seen[$normalized][] = $customer->name;

                    if ($customer->phone_normalized === $normalized) {
                        $unchanged++;

                        continue;
                    }

                    if ($apply) {
                        // forceFill + saveQuietly: this is a data repair, not a
                        // business event, so it must not fire observers or bump
                        // the audit trail for every historic customer.
                        $customer->forceFill(['phone_normalized' => $normalized])->saveQuietly();
                    }

                    $updated++;
                }
            });

        $this->newLine();
        $this->table(['Metric', 'Value'], [
            ['Customers updated', number_format($updated)],
            ['Already correct or blank', number_format($unchanged)],
            ['Unparseable numbers', number_format(count($unparseable))],
        ]);

        if ($unparseable !== []) {
            $this->newLine();
            $this->warn('These numbers could not be read as a phone number and were left alone:');
            $this->table(['ID', 'Customer', 'Phone'], array_slice($unparseable, 0, 25));

            if (count($unparseable) > 25) {
                $this->line(sprintf('   … and %d more.', count($unparseable) - 25));
            }
        }

        if ($this->option('show-duplicates')) {
            $duplicates = array_filter($seen, static fn (array $names): bool => count($names) > 1);

            if ($duplicates === []) {
                $this->info('No duplicate phone numbers found.');
            } else {
                $this->newLine();
                $this->warn(sprintf('%d number(s) are held by more than one customer:', count($duplicates)));
                $this->table(
                    ['Phone', 'Customers'],
                    array_map(
                        static fn (string $phone, array $names): array => [$phone, implode(', ', $names)],
                        array_keys($duplicates),
                        $duplicates,
                    ),
                );
            }
        }

        if (! $apply) {
            $this->newLine();
            $this->info('Dry run — nothing was changed. Re-run with --apply to write these values.');
        }

        return self::SUCCESS;
    }
}
