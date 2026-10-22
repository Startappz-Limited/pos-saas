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
    'app/Http/Controllers/PlatformPaymentWebhookController.php' => [1, 'a payment callback names a checkout request, not a business: the payment is found on our own reference, then settled by asking the M-Pesa API, acting for its business'],
    'app/Actions/Billing/SweepSubscriptions.php' => [1, 'the daily sweep lists which businesses have a subscription, platform-wide; each is then swept acting for its own business'],
    'app/Models/SalePayment.php' => [1, 'a new payment takes its business from its sale'],
    'app/Models/Role.php' => [1, 'a business\'s copy of a role template reads its template, which the role scope hides behind the copy (shared, no business data)'],
    'app/Support/Tenancy/TenantAwarePermissionRegistrar.php' => [1, 'Spatie\'s permission cache is shared by every business, so it loads every role; a user still matches only the roles they hold in the current business (teams)'],
    'app/Models/ProductVariation.php' => [3, 'a new variation takes its business from its product, adds its per-shop rows, and holds a stock figure in its product\'s shop'],
    'app/Models/BusinessInvite.php' => [1, 'an invite link is opened signed out or in another business: its token finds the invite'],
    'app/Actions/Business/AcceptBusinessInvite.php' => [2, 'accepting locks the invite, which belongs to the business being joined, not the current one; and assigns its shops, which the joiner can\'t see until they have them (the business scope still applies)'],
    'app/Models/BusinessExport.php' => [1, 'an export\'s download link works while its business is closing and can\'t be opened; the downloader must be an owner of it'],
    'app/Jobs/BuildBusinessExport.php' => [1, 'marking a failed export runs after the job, outside its business'],
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
