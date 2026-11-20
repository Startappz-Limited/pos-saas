<?php

/**
 * The HasShopStock `stock_quantity` setter (Product and ProductVariation)
 * moves stock as a side effect of saving the model — the exact shortcut
 * stage 11's step 4 follow-up closes: stock changes only through intakes,
 * adjustments (stage 10's approval rule), stocktakes and sales, never a
 * product's own create/update form. Nothing under `app/` should trigger it
 * (factories and seeders, outside `app/`, still may — they aren't a request
 * path).
 *
 * Two sanctioned exceptions:
 * - `App\Support\Legacy\LegacyImport`, the one-off `saas:import-legacy`
 *   command, converting a legacy database once, not a repeatable request path;
 * - `App\Services\Stock\StockLedger`, which writes the real `shop_variation`
 *   column directly (`DB::table('shop_variation')->update(...)`) — not the
 *   model setter at all, and the one place allowed to (its own test,
 *   StockQuantityWriteTest, covers that).
 *
 * `ShopVariation`'s own `stock_quantity` (a real column, a different model)
 * is excluded from the scan entirely, not an exception: it was never the
 * setter in question.
 */
it('uses the stock_quantity setter only from sanctioned callers', function () {
    $root = dirname(__DIR__, 2).'/app';
    $allowed = [
        'Support/Legacy/LegacyImport.php',
        'Services/Stock/StockLedger.php',
    ];
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace($root.'/', '', $file->getPathname());
        if (in_array($relative, $allowed, true)) {
            continue;
        }

        foreach (explode(';', file_get_contents($file->getPathname())) as $statement) {
            if (str_contains($statement, 'ShopVariation')) {
                continue;
            }

            $directSet = preg_match('/->\s*stock_quantity\s*=(?!=)/', $statement);
            $arraySet = str_contains($statement, "'stock_quantity'")
                && preg_match('/->\s*(create|update|fill)\s*\(/', $statement)
                && ! preg_match('/=>\s*(Quantity::class|\'integer\'|\'decimal)/', $statement);

            if ($directSet || $arraySet) {
                $offenders[] = $relative;
            }
        }
    }

    $offenders = array_values(array_unique($offenders));
    expect($offenders)->toBe([], 'Uses the stock_quantity setter outside sanctioned callers: '.implode(', ', $offenders));
});
