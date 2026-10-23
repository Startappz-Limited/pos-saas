<?php

use Database\Seeders\PermissionSeeder;
use Symfony\Component\Finder\Finder;

/**
 * Every permission the code checks exists (fix/notices-and-sidebar-ui). A
 * check naming a permission nobody can hold locks the page for everyone but
 * the platform operator: the stock intake screens checked `stock_intakes.*`
 * and pricing rules `pricing.create`/`pricing.delete`, none of them seeded,
 * while their tests created those permissions themselves.
 */
it('names only seeded permissions in policies, form requests, controllers and middleware', function () {
    $known = array_flip(PermissionSeeder::names());
    $unknown = [];

    foreach (Finder::create()->files()->name('*.php')->in([app_path('Policies'), app_path('Http')]) as $file) {
        preg_match_all("/(?:->can|->cannot|hasPermissionTo|->holds)\\(\\s*(?:\\\$\\w+\\s*,\\s*)?'([a-z_]+\\.[a-z_.-]+)'/", $file->getContents(), $matches);

        foreach ($matches[1] as $permission) {
            if (! isset($known[$permission])) {
                $unknown[] = $file->getRelativePathname().": {$permission}";
            }
        }
    }

    expect(array_values(array_unique($unknown)))->toBe([]);
});
