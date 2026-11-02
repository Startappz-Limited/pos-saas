<?php

/**
 * Every 403 the API controllers raise by hand carries a message (hotfix
 * fix/api-authz, 2 November 2026). `abort(403)` and `abort_unless(..., 403)`
 * without one answer `{"message": ""}`, which leaves the app nothing to show;
 * `$this->authorize()` already says "This action is unauthorized.". A new bare
 * 403 fails this test.
 */
it('has no 403 abort without a message in the API controllers', function () {
    $bare = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app/Http/Controllers/Api'));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        foreach (file($file->getPathname()) as $number => $line) {
            if (preg_match('/\babort(_unless|_if)?\([^;]*\b403\);/', $line) === 1) {
                $bare[] = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname()).':'.($number + 1);
            }
        }
    }

    expect($bare)->toBe([]);
});
