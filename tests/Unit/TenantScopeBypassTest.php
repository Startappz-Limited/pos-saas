<?php

/**
 * Every place app code lifts the tenant scopes: removing all global scopes
 * (withoutGlobalScopes()), or a tenant scope by name, or bypassing the tenant
 * context. Each is listed here with its reason (stage 1a, the
 * "withoutGlobalScopes() misuse" risk). A new one fails this test until it's
 * added here on purpose.
 */
const ALLOWED_SCOPE_BYPASSES = [
    'app/Models/Concerns/BelongsToBusiness.php' => [1, 'a new record takes its business from its shop'],
    'app/Models/Concerns/ResolvesTenantFromRouteBinding.php' => [2, 'a route with no signed-in user (webhook, signed link) finds its record, then acts for its business'],
    'app/Models/SalePayment.php' => [1, 'a new payment takes its business from its sale'],
    'app/Models/ProductVariation.php' => [2, 'a new variation takes its business from its product, and adds its per-shop rows'],
    'app/Listeners/Baileys/HandleGatewayWebhooks.php' => [1, 'a gateway event finds its session by the platform-wide key, then acts for its business'],
];

it('lifts the tenant scopes only where listed', function () {
    $pattern = '/withoutGlobalScopes\(\s*\)|withoutGlobalScope\(\s*(BusinessScope|ShopAccessScope|BusinessOrSharedScope)::class|->bypass\(/';
    $found = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $count = preg_match_all($pattern, file_get_contents($file->getPathname()));
        if ($count > 0) {
            $found[str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname())] = $count;
        }
    }
    ksort($found);

    $allowed = array_map(fn (array $entry): int => $entry[0], ALLOWED_SCOPE_BYPASSES);
    ksort($allowed);

    expect($found)->toBe($allowed, 'Tenant scopes lifted outside the list in tests/Unit/TenantScopeBypassTest.php');
});
