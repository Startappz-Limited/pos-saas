<?php

namespace App\Actions\Product;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\Shop;
use App\Services\ProductService;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Imports products from a CSV file (stage 3, onboarding). The whole file is
 * checked first and nothing is imported if any row is wrong, so a person fixes
 * the file once instead of cleaning up half an import.
 *
 * Columns (header row required, any order): name, selling_price (required);
 * sku, barcode, category, cost_price, wholesale_price, stock, reorder_level,
 * unit (optional). Products are created through the same action as the form;
 * opening stock goes in through the StockLedger, at the cost given.
 */
class ImportProductsFromCsv
{
    public const MAX_ROWS = 2000;

    private const COLUMNS = ['name', 'selling_price', 'sku', 'barcode', 'category', 'cost_price', 'wholesale_price', 'stock', 'reorder_level', 'unit'];

    public function __construct(private ProductService $products, private StockLedger $ledger) {}

    /**
     * @return array{imported: int, errors: list<string>}
     */
    public function execute(string $path, Shop $shop, ?int $userId = null): array
    {
        $parsed = $this->parse($path);

        if ($parsed['errors'] !== []) {
            return ['imported' => 0, 'errors' => $parsed['errors']];
        }

        $errors = $this->validate($parsed['rows']);

        if ($errors !== []) {
            return ['imported' => 0, 'errors' => $errors];
        }

        DB::transaction(function () use ($parsed, $shop, $userId): void {
            $categories = [];

            foreach ($parsed['rows'] as $row) {
                $name = $row['category'] ?: 'General';
                $category = $categories[$name] ??= Category::query()->where('name', $name)->first()
                    ?? Category::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4))]);

                $product = $this->products->createProduct(array_filter([
                    'name' => $row['name'],
                    'sku' => $row['sku'],
                    'barcode' => $row['barcode'],
                    'category_id' => $category->id,
                    'shop_id' => $shop->id,
                    'selling_price' => $row['selling_price'],
                    'cost_price' => $row['cost_price'],
                    'wholesale_price' => $row['wholesale_price'],
                    'reorder_level' => $row['reorder_level'],
                    'unit' => $row['unit'],
                ], fn ($value): bool => $value !== null && $value !== ''));

                $product->syncShopVariationRows();

                if ((int) $row['stock'] > 0) {
                    $this->ledger->receive(
                        $shop->id,
                        $product->createDefaultVariation(),
                        (int) $row['stock'],
                        $row['cost_price'] !== null && $row['cost_price'] !== '' ? (float) $row['cost_price'] : null,
                        StockMovementType::ADJUSTMENT_ADD,
                        ['source_type' => CostLayer::SOURCE_ADJUSTMENT, 'user_id' => $userId, 'notes' => 'Opening stock (CSV import)'],
                    );
                }
            }
        });

        return ['imported' => count($parsed['rows']), 'errors' => []];
    }

    /**
     * A header-and-rows CSV the person can fill in.
     */
    public static function template(): string
    {
        return implode(',', self::COLUMNS)."\n"
            .'"Sample item",250.00,SKU-001,,General,150.00,200.00,20,5,pc'."\n";
    }

    /**
     * @return array{rows: list<array<string, ?string>>, errors: list<string>}
     */
    private function parse(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['rows' => [], 'errors' => [__('The file could not be read.')]];
        }

        $header = fgetcsv($handle, 0, ',', '"', '');
        // A spreadsheet's byte-order mark would hide the first column's name.
        $header = array_map(fn ($name): string => Str::of((string) $name)->replace("\xEF\xBB\xBF", '')->trim()->lower()->toString(), $header ?: []);

        foreach (['name', 'selling_price'] as $required) {
            if (! in_array($required, $header, true)) {
                fclose($handle);

                return ['rows' => [], 'errors' => [__('The first row must name the columns, including ":column".', ['column' => $required])]];
            }
        }

        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;

            if ($values === [null] || count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                return ['rows' => [], 'errors' => [__('A file can hold up to :max products. Split it and import the parts.', ['max' => self::MAX_ROWS])]];
            }

            $row = array_fill_keys(self::COLUMNS, null);
            foreach ($header as $index => $column) {
                if (in_array($column, self::COLUMNS, true)) {
                    $value = trim((string) ($values[$index] ?? ''));
                    $row[$column] = $value === '' ? null : $value;
                }
            }
            $row['_line'] = (string) $line;
            $rows[] = $row;
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $rows === [] ? [__('The file has no products.')] : []];
    }

    /**
     * @param  list<array<string, ?string>>  $rows
     * @return list<string>
     */
    private function validate(array $rows): array
    {
        $errors = [];
        $seen = [];
        $existing = Product::query()->pluck('sku')->filter()->map(fn ($sku): string => Str::lower($sku))->flip();

        foreach ($rows as $row) {
            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                'selling_price' => ['required', 'numeric', 'min:0'],
                'sku' => ['nullable', 'string', 'max:255'],
                'barcode' => ['nullable', 'string', 'max:255'],
                'category' => ['nullable', 'string', 'max:255'],
                'cost_price' => ['nullable', 'numeric', 'min:0'],
                'wholesale_price' => ['nullable', 'numeric', 'min:0'],
                'stock' => ['nullable', 'integer', 'min:0'],
                'reorder_level' => ['nullable', 'integer', 'min:0'],
                'unit' => ['nullable', 'string', 'max:255'],
            ]);

            foreach ($validator->errors()->all() as $message) {
                $errors[] = __('Line :line: :message', ['line' => $row['_line'], 'message' => $message]);
            }

            $sku = $row['sku'] === null ? null : Str::lower($row['sku']);
            if ($sku !== null && (isset($seen[$sku]) || $existing->has($sku))) {
                $errors[] = __('Line :line: the SKU ":sku" is already used.', ['line' => $row['_line'], 'sku' => $row['sku']]);
            }
            if ($sku !== null) {
                $seen[$sku] = true;
            }

            if (count($errors) >= 25) {
                $errors[] = __('Fix these first; more may follow.');

                break;
            }
        }

        return $errors;
    }
}
