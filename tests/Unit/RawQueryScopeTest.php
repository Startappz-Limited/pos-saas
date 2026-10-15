<?php

/**
 * Every place app code queries a table through the query builder (DB::table,
 * DB::select and friends), which no model scope reaches (stage 1c, the "a
 * missed raw query" risk). Each file is listed with how many such calls it
 * makes and why they are safe: either the file restricts them with
 * TenantContext::restrictToShops()/restrictToBusiness(), or they act on ids
 * that already came through scoped models. A new call fails this test until
 * it's added here on purpose.
 *
 * Raw clauses on Eloquent queries (Sale::selectRaw(...)) aren't counted: the
 * model's tenant scope still applies to them.
 */
const SCOPED = 'restricted with TenantContext::restrictToShops()';

const ALLOWED_RAW_QUERIES = [
    // Listing and aggregate reads, restricted to the viewer's shops.
    'app/Actions/CheckLowStockLevels.php' => [1, SCOPED],
    'app/Actions/CreateInventorySnapshot.php' => [2, SCOPED],
    'app/Console/Commands/AssignProductShopFromSales.php' => [2, SCOPED],
    'app/Http/Controllers/Api/EcommerceOrderController.php' => [1, SCOPED.' (applyShopFilter)'],
    'app/Http/Controllers/Api/ReportController.php' => [3, SCOPED],
    'app/Http/Controllers/DashboardController.php' => [8, SCOPED.' (applyShopFilter)'],
    'app/Http/Controllers/EcommerceOrderController.php' => [1, SCOPED.' (applyShopFilter)'],
    'app/Http/Controllers/SaleController.php' => [3, SCOPED.' (applyShopFilter)'],
    'app/Services/ReportService.php' => [5, SCOPED.', plus the explicit allowed_shop_ids list'],
    'app/Services/VatReportService.php' => [2, SCOPED],

    // Internal reads and writes by id, on ids that came through scoped models.
    'app/Actions/ConvertEcommerceOrderToSale.php' => [1, 'the register of a session the action just found or opened'],
    'app/Actions/PurchaseReturn/MarkPurchaseReturnShippedAction.php' => [1, 'the cost layer of a scoped intake'],
    'app/Console/Commands/BackfillShopProductPivot.php' => [2, 'pivot rows for a scoped product and its sync record\'s shop'],
    'app/Console/Commands/RecomputeSaleProfit.php' => [3, 'allocations and layers of scoped sales\' lines'],
    'app/Models/Product.php' => [5, 'the product\'s own shop and per-shop rows'],
    'app/Models/Register.php' => [4, 'the default register of a shop the caller names'],
    'app/Services/InvoiceNumberService.php' => [6, 'the invoice series of the sale\'s shop and register'],
    'app/Services/ProductService.php' => [1, 'per-shop stock of scoped products in the viewer\'s shops (StockQuery)'],
    'app/Services/Stock/StockLedger.php' => [26, 'stock, layers and allocations of the shop and variation the caller passes'],
    'app/Services/Stock/StockQuery.php' => [4, 'correlated to a scoped product or variation; the viewer\'s shops'],
    'app/Support/Tenancy/TenantContext.php' => [2, 'builds the accessible-shops subquery itself'],

    // Platform-wide repairs.
    'app/Console/Commands/BackfillAuditBusiness.php' => [3, 'maps audit rows of every business to their business, from shops and records'],

    // Not tenant data.
    'app/Console/Commands/BuildDatasetCommand.php' => [1, 'row counts of a _testing dataset database'],
];

/**
 * The file's code with its comments removed.
 */
function codeWithoutComments(string $path): string
{
    $code = '';
    foreach (token_get_all(file_get_contents($path)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $code .= is_array($token) ? $token[1] : $token;
    }

    return $code;
}

it('queries tables through the query builder only where listed', function () {
    $pattern = '/DB::(table|select|selectOne|statement|update|insert|delete|unprepared|cursor)\(/';
    $root = dirname(__DIR__, 2);
    $found = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $count = preg_match_all($pattern, codeWithoutComments($file->getPathname()));
        if ($count > 0) {
            $found[str_replace($root.'/', '', $file->getPathname())] = $count;
        }
    }
    ksort($found);

    $allowed = array_map(fn (array $entry): int => $entry[0], ALLOWED_RAW_QUERIES);
    ksort($allowed);

    expect($found)->toBe($allowed, 'Query-builder queries outside the list in tests/Unit/RawQueryScopeTest.php');
});

it('restricts every file listed as scoped', function () {
    $root = dirname(__DIR__, 2);

    foreach (ALLOWED_RAW_QUERIES as $path => [, $reason]) {
        if (! str_starts_with($reason, SCOPED)) {
            continue;
        }

        expect(str_contains(codeWithoutComments("{$root}/{$path}"), 'restrictToShops('))->toBeTrue("{$path} is listed as scoped but never restricts");
    }
});
