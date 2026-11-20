<?php

/**
 * shop_variation.stock_quantity changes only through StockLedger (stage 1b;
 * an architecture test since stage 11, step 4): scans every `app/` file for
 * a write call (update, insert, insertOrIgnore, increment, decrement,
 * upsert) in the same statement as a `shop_variation` reference.
 *
 * Two sanctioned exceptions, both pre-existing and already covered by their
 * own tests:
 * - `App\Support\Legacy\LegacyImport`, the one-off `saas:import-legacy`
 *   command, which seeds the table directly while converting a legacy
 *   database (before the ledger, or any tenant scope, has anything to act on);
 * - `Product::syncShopVariationRows()`'s `insertOrIgnore`, which only ever
 *   creates a zero-stock bookkeeping row (ignored for one that already
 *   exists) — the same "insert at zero, never update" pattern
 *   `StockLedger::lockStock()` itself uses, never a real stock figure.
 */
it('writes shop_variation only from StockLedger', function () {
    $root = dirname(__DIR__, 2).'/app';
    $allowed = [
        'Services/Stock/StockLedger.php',
        'Support/Legacy/LegacyImport.php',
        'Models/Product.php',
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
            if (str_contains($statement, 'shop_variation')
                && preg_match('/->\s*(update|insert|insertOrIgnore|increment|decrement|upsert)\s*\(/', $statement)) {
                $offenders[] = $relative;
            }
        }
    }

    $offenders = array_values(array_unique($offenders));
    expect($offenders)->toBe([], 'Writes shop_variation outside StockLedger: '.implode(', ', $offenders));
});
