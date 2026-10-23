<?php

namespace App\Actions\Product;

use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\CostLayer;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopVariation;
use App\Services\ProductService;
use App\Services\Stock\StockLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Imports products from a CSV file (stage 3, onboarding; reworked in
 * fix/product-stock-import). Two steps:
 *
 * 1. analyse(): the whole file is checked first. A missing required column, or
 *    any line with a bad value, refuses the file: nothing is imported, so a
 *    person fixes it once instead of cleaning up half an import. Column names
 *    are matched loosely ("Stock Quantity" is `stock`) and through common
 *    alternatives; columns the import doesn't use are listed as a warning.
 *    Rows whose SKU belongs to an existing product are reported, not refused.
 * 2. import(): new products are created through the same action as the form,
 *    with their opening stock through the StockLedger at the cost given. For
 *    existing products the caller decides: refuse the file, skip them, or
 *    update them, which SETS their stock in the chosen shop to the file's
 *    figure (re-uploading the same file changes nothing) and their threshold
 *    there. Their name, prices, cost and category are left as they are.
 */
class ImportProductsFromCsv
{
    public const MAX_ROWS = 2000;

    /** What to do with rows whose SKU already exists. */
    public const REFUSE_EXISTING = 'refuse';

    public const UPDATE_EXISTING = 'update';

    public const SKIP_EXISTING = 'skip';

    /**
     * The columns, in template order: whether required, what they hold, an
     * example, and the other header names accepted for them.
     *
     * @var array<string, array{required: bool, description: string, example: string, aliases: list<string>}>
     */
    private const COLUMNS = [
        'name' => ['required' => true, 'description' => 'The product\'s name.', 'example' => 'Wireless Mouse', 'aliases' => ['product', 'product_name', 'item', 'item_name']],
        'selling_price' => ['required' => true, 'description' => 'The price a customer pays.', 'example' => '1500.00', 'aliases' => ['price', 'retail_price', 'sale_price']],
        'sku' => ['required' => false, 'description' => 'Your product code. Used to recognise products already in the system; made up when left empty.', 'example' => 'MOU-001', 'aliases' => ['code', 'product_code', 'item_code']],
        'barcode' => ['required' => false, 'description' => 'The barcode printed on the item.', 'example' => '6001234567890', 'aliases' => ['ean', 'upc']],
        'category' => ['required' => false, 'description' => 'A category name; created when new, "General" when empty.', 'example' => 'Accessories', 'aliases' => ['category_name']],
        'cost_price' => ['required' => false, 'description' => 'What one item costs you to buy.', 'example' => '900.00', 'aliases' => ['cost', 'buying_price', 'purchase_price']],
        'wholesale_price' => ['required' => false, 'description' => 'The price for wholesale customers.', 'example' => '1300.00', 'aliases' => ['wholesale']],
        'stock' => ['required' => false, 'description' => 'How many items are in the chosen shop now. Empty is 0 for a new product, and no change for an existing one.', 'example' => '20', 'aliases' => ['stock_quantity', 'quantity', 'qty', 'opening_stock', 'stock_level', 'on_hand']],
        'reorder_level' => ['required' => false, 'description' => 'The low-stock threshold: an alert when stock falls to this. Empty is 10 for a new product.', 'example' => '5', 'aliases' => ['low_stock_threshold', 'threshold', 'min_stock', 'minimum_stock', 'reorder_point', 'low_stock']],
        'unit' => ['required' => false, 'description' => 'How it is counted, e.g. piece, kg, box.', 'example' => 'piece', 'aliases' => ['uom', 'unit_of_measure']],
    ];

    public function __construct(private ProductService $products, private StockLedger $ledger) {}

    /**
     * The columns for the import page's help table.
     *
     * @return list<array{name: string, required: bool, description: string, example: string, aliases: list<string>}>
     */
    public static function columns(): array
    {
        return collect(self::COLUMNS)->map(fn (array $column, string $name): array => ['name' => $name, ...$column])->values()->all();
    }

    /**
     * A file to fill in: every column, a full example row and a minimal one.
     */
    public static function template(): string
    {
        $full = collect(self::COLUMNS)->map(fn (array $column): string => $column['example']);
        $minimal = collect(self::COLUMNS)->map(fn (array $column, string $name): string => $column['required'] ? ($name === 'name' ? 'USB Cable 1m' : '300.00') : '');

        return collect([array_keys(self::COLUMNS), $full->all(), $minimal->all()])
            ->map(fn (array $cells): string => implode(',', array_map(fn ($cell): string => str_contains((string) $cell, ',') || str_contains((string) $cell, ' ') ? '"'.$cell.'"' : (string) $cell, $cells)))
            ->implode("\n")."\n";
    }

    /**
     * Checks the file: errors refuse it; ignored columns are a warning; rows
     * whose SKU exists are listed for the caller to decide on.
     *
     * @return array{rows: list<array<string, ?string>>, errors: list<string>, ignored: list<string>, existing: array<string, Product>}
     */
    public function analyse(string $path): array
    {
        $parsed = $this->parse($path);

        if ($parsed['errors'] !== []) {
            return [...$parsed, 'existing' => []];
        }

        [$errors, $existing] = $this->validate($parsed['rows']);

        return ['rows' => $parsed['rows'], 'errors' => $errors, 'ignored' => $parsed['ignored'], 'existing' => $errors === [] ? $existing : []];
    }

    /**
     * Imports an analysed file into $shop.
     *
     * @param  array{rows: list<array<string, ?string>>, existing: array<string, Product>}  $analysis
     * @param  self::*_EXISTING  $existingMode
     * @return array{created: int, updated: int, unchanged: int, skipped: int, opening_stock: int, errors: list<string>}
     */
    public function import(array $analysis, Shop $shop, ?int $userId, string $existingMode = self::REFUSE_EXISTING): array
    {
        $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'opening_stock' => 0, 'errors' => []];
        $existing = $analysis['existing'];

        if ($existingMode === self::REFUSE_EXISTING && $existing !== []) {
            foreach ($analysis['rows'] as $row) {
                if ($row['sku'] !== null && isset($existing[Str::lower($row['sku'])])) {
                    $result['errors'][] = __('Line :line: the SKU ":sku" already exists. Update its stock with a stock adjustment, or through the import page.', ['line' => $row['_line'], 'sku' => $row['sku']]);
                }
            }

            return $result;
        }

        DB::transaction(function () use ($analysis, $existing, $existingMode, $shop, $userId, &$result): void {
            $categories = [];

            foreach ($analysis['rows'] as $row) {
                $product = $row['sku'] !== null ? ($existing[Str::lower($row['sku'])] ?? null) : null;

                if ($product !== null) {
                    if ($existingMode === self::SKIP_EXISTING || $product->has_variations) {
                        $result['skipped']++;

                        continue;
                    }

                    $this->updateExisting($product, $row, $shop, $userId) ? $result['updated']++ : $result['unchanged']++;

                    continue;
                }

                $this->create($row, $shop, $userId, $categories);
                $result['created']++;
                $result['opening_stock'] += max(0, (int) $row['stock']);
            }
        });

        return $result;
    }

    /**
     * One step for callers with no confirmation screen (the API): existing SKUs
     * refuse the file.
     *
     * @return array{imported: int, opening_stock: int, ignored: list<string>, errors: list<string>}
     */
    public function execute(string $path, Shop $shop, ?int $userId = null): array
    {
        $analysis = $this->analyse($path);

        if ($analysis['errors'] !== []) {
            return ['imported' => 0, 'opening_stock' => 0, 'ignored' => $analysis['ignored'], 'errors' => $analysis['errors']];
        }

        $result = $this->import($analysis, $shop, $userId);

        return ['imported' => $result['created'], 'opening_stock' => $result['opening_stock'], 'ignored' => $analysis['ignored'], 'errors' => $result['errors']];
    }

    /**
     * What updating an existing product would change in $shop: its stock and
     * threshold now, and in the file (null where the file leaves it alone).
     *
     * @param  array<string, ?string>  $row
     * @return array{stock: int, file_stock: ?int, threshold: int, file_threshold: ?int, has_variations: bool, changes: bool}
     */
    public function preview(Product $product, array $row, Shop $shop): array
    {
        $current = $this->shopRow($product, $shop);
        $stock = (int) ($current?->stock_quantity ?? 0);
        $threshold = (int) ($current?->reorder_level ?? $product->reorder_level);
        $fileStock = $row['stock'] === null ? null : (int) $row['stock'];
        $fileThreshold = $row['reorder_level'] === null ? null : (int) $row['reorder_level'];

        return [
            'stock' => $stock,
            'file_stock' => $fileStock,
            'threshold' => $threshold,
            'file_threshold' => $fileThreshold,
            'has_variations' => (bool) $product->has_variations,
            'changes' => ($fileStock !== null && $fileStock !== $stock) || ($fileThreshold !== null && $fileThreshold !== $threshold),
        ];
    }

    /**
     * @param  array<string, ?string>  $row
     * @param  array<string, Category>  $categories
     */
    private function create(array $row, Shop $shop, ?int $userId, array &$categories): void
    {
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
                $row['cost_price'] !== null ? (float) $row['cost_price'] : null,
                StockMovementType::ADJUSTMENT_ADD,
                ['source_type' => CostLayer::SOURCE_ADJUSTMENT, 'user_id' => $userId, 'notes' => 'Opening stock (CSV import)'],
            );
        }
    }

    /**
     * Sets an existing product's stock in $shop to the file's figure and its
     * threshold there; true when something changed.
     *
     * @param  array<string, ?string>  $row
     */
    private function updateExisting(Product $product, array $row, Shop $shop, ?int $userId): bool
    {
        $before = $this->preview($product, $row, $shop);

        if (! $before['changes']) {
            return false;
        }

        // The shop may not carry the product yet.
        $product->shops()->syncWithoutDetaching([$shop->id]);
        $product->syncShopVariationRows();
        $variation = $product->createDefaultVariation();

        if ($before['file_stock'] !== null && $before['file_stock'] !== $before['stock']) {
            $this->ledger->setLevel($shop->id, $variation, $before['file_stock'], ['user_id' => $userId, 'notes' => 'Stock level set by CSV import']);
        }

        if ($before['file_threshold'] !== null && $before['file_threshold'] !== $before['threshold']) {
            ShopVariation::query()->where('shop_id', $shop->id)->where('variation_id', $variation->id)
                ->update(['reorder_level' => $before['file_threshold']]);
        }

        return true;
    }

    private function shopRow(Product $product, Shop $shop): ?ShopVariation
    {
        $variation = $product->defaultVariation()->first();

        return $variation === null ? null
            : ShopVariation::query()->where('shop_id', $shop->id)->where('variation_id', $variation->id)->first();
    }

    /**
     * A header's canonical column name, or null when the import doesn't use it.
     */
    private static function canonical(string $header): ?string
    {
        $key = Str::of($header)->replace("\xEF\xBB\xBF", '')->trim()->lower()->replaceMatches('/[\s\-\.]+/', '_')->trim('_')->toString();

        foreach (self::COLUMNS as $name => $column) {
            if ($key === $name || in_array($key, $column['aliases'], true)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array{rows: list<array<string, ?string>>, errors: list<string>, ignored: list<string>}
     */
    private function parse(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['rows' => [], 'errors' => [__('The file could not be read.')], 'ignored' => []];
        }

        $header = fgetcsv($handle, 0, ',', '"', '') ?: [];
        $positions = [];
        $ignored = [];
        $errors = [];

        foreach ($header as $index => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $column = self::canonical($name);

            if ($column === null) {
                $ignored[] = $name;
            } elseif (isset($positions[$column])) {
                $errors[] = __('Two columns mean ":column": ":first" and ":second". Keep one.', ['column' => $column, 'first' => $header[$positions[$column]], 'second' => $name]);
            } else {
                $positions[$column] = $index;
            }
        }

        foreach (self::COLUMNS as $name => $column) {
            if ($column['required'] && ! isset($positions[$name])) {
                $errors[] = __('The file needs a ":column" column (also accepted: :aliases). Download the template to see the columns.', ['column' => $name, 'aliases' => implode(', ', $column['aliases'])]);
            }
        }

        if ($errors !== []) {
            fclose($handle);

            return ['rows' => [], 'errors' => $errors, 'ignored' => $ignored];
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

                return ['rows' => [], 'errors' => [__('A file can hold up to :max products. Split it and import the parts.', ['max' => self::MAX_ROWS])], 'ignored' => $ignored];
            }

            $row = array_fill_keys(array_keys(self::COLUMNS), null);
            foreach ($positions as $column => $index) {
                $value = trim((string) ($values[$index] ?? ''));
                $row[$column] = $value === '' ? null : $value;
            }
            $row['_line'] = (string) $line;
            $rows[] = $row;
        }

        fclose($handle);

        return ['rows' => $rows, 'errors' => $rows === [] ? [__('The file has no products.')] : [], 'ignored' => $ignored];
    }

    /**
     * @param  list<array<string, ?string>>  $rows
     * @return array{0: list<string>, 1: array<string, Product>}
     */
    private function validate(array $rows): array
    {
        $errors = [];
        $seen = [];
        $skus = collect($rows)->pluck('sku')->filter()->map(fn (string $sku): string => Str::lower($sku))->unique();
        /** @var Collection<string, Product> $existing */
        $existing = $skus->isEmpty() ? collect() : Product::query()->whereIn(DB::raw('lower(sku)'), $skus->all())->get()
            ->keyBy(fn (Product $product): string => Str::lower($product->sku));

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
            if ($sku !== null && isset($seen[$sku])) {
                $errors[] = __('Line :line: the SKU ":sku" is on line :first too. Each product once.', ['line' => $row['_line'], 'sku' => $row['sku'], 'first' => $seen[$sku]]);
            }
            if ($sku !== null) {
                $seen[$sku] ??= $row['_line'];
            }

            if (count($errors) >= 25) {
                $errors[] = __('Fix these first; more may follow.');

                break;
            }
        }

        return [$errors, $existing->all()];
    }
}
