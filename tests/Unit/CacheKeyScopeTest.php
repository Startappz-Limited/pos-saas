<?php

/**
 * Every cache key includes the business (stage 1c): a file that uses the
 * cache builds its keys with TenantContext::cacheKey(). A global key would
 * hand one business's cached value to every other.
 */
it('builds every cache key through TenantContext::cacheKey()', function () {
    $root = dirname(__DIR__, 2);
    $unkeyed = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $code = file_get_contents($file->getPathname());
        if (preg_match('/Cache::|cache\(\)/', $code) && ! str_contains($code, 'cacheKey(')) {
            $unkeyed[] = str_replace($root.'/', '', $file->getPathname());
        }
    }

    expect($unkeyed)->toBe([], 'Cache used without TenantContext::cacheKey()');
});
