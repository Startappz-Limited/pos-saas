<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fills `business_id` on audit rows written before audit rows carried it
 * (stage 1c). Dry run by default.
 *
 * A row's business comes from, in order: its shop; the audited record's own
 * business (for business-owned records, and shops themselves); and, when the
 * database holds a single business, that business. Rows left over (a user
 * record in a database with several businesses, say) stay empty: reads are
 * limited to a business, so they are shown to none.
 *
 * Audit rows are otherwise immutable; this only fills the column that says
 * which business may read them, never what they record.
 */
class BackfillAuditBusiness extends Command
{
    protected $signature = 'audit:backfill-business
        {--apply : Write the changes (without it, only report them)}';

    protected $description = 'Fill business_id on audit rows from their shop or audited record (dry run unless --apply).';

    public function handle(): int
    {
        $audit = DB::connection('audit');
        $pending = $audit->table('audit_logs')->whereNull('business_id');

        $total = (clone $pending)->count();
        if ($total === 0) {
            $this->info('Every audit row has its business.');

            return self::SUCCESS;
        }

        $shopBusiness = DB::table('shops')->pluck('business_id', 'id');
        $businesses = DB::table('businesses')->pluck('id');
        $only = $businesses->count() === 1 ? (int) $businesses->first() : null;

        $resolved = ['shop' => 0, 'record' => 0, 'only business' => 0];
        $updates = [];
        $unresolved = 0;

        foreach ((clone $pending)->orderBy('id')->lazyById(500) as $row) {
            [$businessId, $how] = $this->resolve($row, $shopBusiness, $only);

            if ($businessId === null) {
                $unresolved++;

                continue;
            }

            $resolved[$how]++;
            $updates[$businessId][] = $row->id;
        }

        $this->table(['Resolved by', 'Rows'], [
            ...collect($resolved)->map(fn (int $rows, string $how): array => [$how, $rows])->values()->all(),
            ['unresolved (left empty)', $unresolved],
        ]);

        if (! $this->option('apply')) {
            $this->warn("Dry run: {$total} row(s) without a business. Run with --apply to write.");

            return self::SUCCESS;
        }

        foreach ($updates as $businessId => $ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                $audit->table('audit_logs')->whereIn('id', $chunk)->whereNull('business_id')->update(['business_id' => $businessId]);
            }
        }

        $this->info(sprintf('Filled the business on %d row(s).', $total - $unresolved));

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, int>  $shopBusiness
     * @return array{0: int|null, 1: string|null}
     */
    private function resolve(object $row, $shopBusiness, ?int $only): array
    {
        if ($row->shop_id !== null && isset($shopBusiness[$row->shop_id])) {
            return [(int) $shopBusiness[$row->shop_id], 'shop'];
        }

        $recordBusiness = $this->recordBusiness($row->auditable_type, (int) $row->auditable_id);
        if ($recordBusiness !== null) {
            return [$recordBusiness, 'record'];
        }

        return $only !== null ? [$only, 'only business'] : [null, null];
    }

    /** @var array<string, string|null> table of each audited type that carries business_id */
    private array $businessTables = [];

    private function recordBusiness(string $type, int $id): ?int
    {
        if (! array_key_exists($type, $this->businessTables)) {
            $table = class_exists($type) && is_subclass_of($type, Model::class) ? (new $type)->getTable() : null;
            $this->businessTables[$type] = $table !== null && Schema::hasColumn($table, 'business_id') ? $table : null;
        }

        $table = $this->businessTables[$type];
        if ($table === null) {
            return null;
        }

        $businessId = DB::table($table)->where('id', $id)->value('business_id');

        return $businessId !== null ? (int) $businessId : null;
    }
}
