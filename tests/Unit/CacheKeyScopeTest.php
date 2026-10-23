<?php

/**
 * Every cache key includes the business (stage 1c): a file that uses the
 * cache builds its keys with TenantContext::cacheKey(). A global key would
 * hand one business's cached value to every other.
 */
const PLATFORM_WIDE_CACHE = [
    // Signup (Phase 6) runs before any business exists, so cacheKey() would refuse.
    'app/Support/Signup/EmailPolicy.php' => 'the disposable-domain list and DNS lookups are the same for everyone',
    'app/Support/Signup/VerificationLink.php' => 'a per-user nonce, keyed by the user id; a user belongs to no single business',
    'app/Console/Commands/LoadDisposableDomains.php' => 'clears the platform-wide disposable-domain list',
];

it('builds every cache key through TenantContext::cacheKey()', function () {
    $root = dirname(__DIR__, 2);
    $unkeyed = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path = str_replace(str_replace('\\', '/', $root).'/', '', str_replace('\\', '/', $file->getPathname()));
        $code = file_get_contents($file->getPathname());
        if (preg_match('/Cache::|cache\(\)/', $code) && ! str_contains($code, 'cacheKey(') && ! isset(PLATFORM_WIDE_CACHE[$path])) {
            $unkeyed[] = $path;
        }
    }

    expect($unkeyed)->toBe([], 'Cache used without TenantContext::cacheKey()');
});
