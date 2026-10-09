<?php

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessExport;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Builds the ZIP a business owner downloads before closing their business:
 * one CSV per kind of record, plus business.json and a README.
 *
 * Runs in a queued job with no one signed in, so the shop and business
 * scopes do not apply: every query here filters on the business explicitly,
 * through its shop ids or business_id. Secrets (passwords, tokens, shop
 * integration settings) are left out, and cells that a spreadsheet would run
 * as a formula are neutralised.
 */
class BusinessExporter
{
    /**
     * Columns never written to an export, in any table.
     *
     * @var array<int, string>
     */
    private const SECRET_COLUMNS = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'settings',
        'credentials',
        'session_key',
        'qr_code',
        'access_token',
        'refresh_token',
        'api_key',
        'api_secret',
        'webhook_secret',
        'consumer_key',
        'consumer_secret',
        'token',
    ];

    private const CHUNK = 1000;

    /**
     * Writes the export's ZIP and returns its path on BusinessExport::DISK.
     */
    public function build(BusinessExport $export): string
    {
        $business = Business::withTrashed()->findOrFail($export->business_id);
        $disk = Storage::disk(BusinessExport::DISK);
        $path = "business-exports/{$business->uuid}/{$export->uuid}.zip";
        $workDir = storage_path('app/tmp/business-export-'.$export->uuid);

        $disk->makeDirectory(dirname($path));
        File::ensureDirectoryExists($workDir);

        try {
            $zip = new ZipArchive;
            if ($zip->open($disk->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException("Cannot create {$path}");
            }

            $counts = [];
            foreach ($this->datasets($business) as $name => $query) {
                $file = "{$workDir}/{$name}.csv";
                $counts[$name] = $this->writeCsv($query, $file);
                $zip->addFile($file, "{$name}.csv");
            }

            $zip->addFromString('business.json', json_encode($this->summary($business, $counts), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $zip->addFromString('README.txt', $this->readme($business));

            if (! $zip->close()) {
                throw new RuntimeException("Cannot write {$path}");
            }
        } catch (\Throwable $e) {
            $disk->delete($path);

            throw $e;
        } finally {
            File::deleteDirectory($workDir);
        }

        return $path;
    }

    /**
     * Every kind of record the business holds, as file name => query.
     *
     * @return array<string, Builder>
     */
    public function datasets(Business $business): array
    {
        $shopIds = $business->allShopIds();
        $byBusiness = fn (string $table): Builder => DB::table($table)->where('business_id', $business->id);
        $byShop = fn (string $table): Builder => DB::table($table)->whereIn('shop_id', $shopIds);
        $children = fn (string $table, string $foreignKey, Builder $parents): Builder => DB::table($table)
            ->whereIn($foreignKey, (clone $parents)->select('id'));

        $products = DB::table('products')->where(function (Builder $query) use ($shopIds): void {
            $query->whereIn('shop_id', $shopIds)
                ->orWhereIn('id', DB::table('shop_product')->whereIn('shop_id', $shopIds)->select('product_id'));
        });

        $sets = [
            'shops' => DB::table('shops')->where('business_id', $business->id),
            'staff' => $byBusiness('users'),
            'roles' => $byBusiness('roles'),
            'staff_roles' => DB::table('model_has_roles')->where('business_id', $business->id),
            'staff_shops' => $byShop('shop_user'),
            'suppliers' => $byBusiness('suppliers'),
            'categories' => $byBusiness('categories'),
            'attributes' => $byBusiness('attributes'),
            'pricing_rules' => $byBusiness('pricing_rules'),
            'expense_categories' => $byBusiness('expense_categories'),
            'delivery_companies' => $byBusiness('delivery_companies'),
            'sale_sources' => $byBusiness('sale_sources'),
            'products' => $products,
            'product_variations' => $children('product_variations', 'product_id', $products),
            'shop_products' => $byShop('shop_product'),
            'customers' => $byShop('customers'),
            'sales' => $byShop('sales'),
            'sale_items' => $children('sale_items', 'sale_id', $byShop('sales')),
            'sale_payments' => $children('sale_payments', 'sale_id', $byShop('sales')),
            'payments' => $byShop('payments'),
            'returns' => $byShop('returns'),
            'return_items' => $children('return_items', 'return_id', $byShop('returns')),
            'refunds' => $byShop('refunds'),
            'credit_accounts' => $byShop('credit_accounts'),
            'credit_transactions' => $byShop('credit_transactions'),
            'credit_payments' => $byShop('credit_payments'),
            'credit_limit_requests' => $byShop('credit_limit_requests'),
            'expenses' => $byShop('expenses'),
            'expense_approvals' => $children('expense_approval_history', 'expense_id', $byShop('expenses')),
            'cash_registers' => $byShop('cash_registers'),
            'purchase_orders' => $byShop('purchase_orders'),
            'purchase_order_items' => $children('purchase_order_items', 'purchase_order_id', $byShop('purchase_orders')),
            'purchase_returns' => $byShop('purchase_returns'),
            'purchase_return_items' => $children('purchase_return_items', 'purchase_return_id', $byShop('purchase_returns')),
            'stock_intakes' => $byShop('stock_intakes'),
            'stock_adjustments' => $byShop('stock_adjustments'),
            'stock_adjustment_items' => $byShop('stock_adjustment_items'),
            'stock_movements' => $byShop('stock_movements'),
            'ecommerce_orders' => $byShop('ecommerce_orders'),
            'ecommerce_order_items' => $children('ecommerce_order_items', 'ecommerce_order_id', $byShop('ecommerce_orders')),
            'abandoned_carts' => $byShop('abandoned_carts'),
            'campaigns' => $byShop('campaigns'),
            'campaign_posts' => $byShop('campaign_posts'),
            'whatsapp_contacts' => $byShop('baileys_contacts'),
            'whatsapp_messages' => $byShop('baileys_messages'),
            'whatsapp_cloud_messages' => $byShop('whatsapp_messages'),
        ];

        return array_filter($sets, fn (Builder $query): bool => Schema::hasTable($query->from));
    }

    /**
     * Streams a query into a CSV file in chunks and returns the row count.
     */
    private function writeCsv(Builder $query, string $file): int
    {
        $columns = array_values(array_diff(Schema::getColumnListing($query->from), self::SECRET_COLUMNS));
        $handle = fopen($file, 'w');
        fputcsv($handle, $columns);
        $count = 0;

        $write = function ($rows) use ($handle, $columns, &$count): void {
            foreach ($rows as $row) {
                $row = (array) $row;
                fputcsv($handle, array_map(fn (string $column) => $this->cell($row[$column] ?? null), $columns));
                $count++;
            }
        };

        $query = (clone $query)->select(array_map(fn (string $column) => "{$query->from}.{$column}", $columns));

        if (in_array('id', $columns, true)) {
            $query->chunkById(self::CHUNK, Closure::fromCallable($write), "{$query->from}.id", 'id');
        } else {
            $write($query->get());
        }

        fclose($handle);

        return $count;
    }

    /**
     * A text cell starting with = + - @ (or a tab/return) is run as a formula
     * by Excel and friends; prefix it so it stays text. Numbers are left alone.
     */
    private function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || is_numeric($value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, mixed>
     */
    private function summary(Business $business, array $counts): array
    {
        return [
            'business' => [
                'uuid' => $business->uuid,
                'name' => $business->name,
                'created_at' => $business->created_at?->toIso8601String(),
                'closing_requested_at' => $business->closing_requested_at?->toIso8601String(),
            ],
            'exported_at' => now()->toIso8601String(),
            'application' => config('app.name'),
            'record_counts' => $counts,
        ];
    }

    private function readme(Business $business): string
    {
        return Str::of(<<<'TXT'
            Data export for :business
            Exported :date by :app.

            Each .csv file holds one kind of record (sales, customers, products, ...),
            one row per record, with the column names on the first line. Rows refer to
            each other by their "id" columns: for example sale_items.sale_id is the id of
            a row in sales.csv. business.json lists how many records each file holds.

            Passwords, sign-in tokens and the keys of your shop integrations are not
            included. Text that a spreadsheet would treat as a formula starts with an
            apostrophe (').

            This file contains your customers' and staff's personal details: store it
            securely. You may need to keep sales and tax records for several years
            (in Kenya, 5 years for VAT records).
            TXT)
            ->replace(':business', $business->name)
            ->replace(':date', now()->toDayDateTimeString())
            ->replace(':app', config('app.name'))
            ->toString();
    }
}
