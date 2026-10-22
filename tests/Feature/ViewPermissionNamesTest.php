<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\Finder\Finder;

/**
 * Every permission a view checks exists (Phase 5). A `@can('name')` naming a
 * permission nobody can hold hides its link from everyone but the platform
 * operator, as `attributes.view`, `attributes.create` and `stock.view` did
 * in the sidebar. A check with a model argument (`@can('update', $role)`)
 * asks a policy and isn't listed here.
 */
it('names only seeded permissions or defined gates in @can, @cannot and @canany', function () {
    $known = array_flip(PermissionSeeder::names());
    $unknown = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        $code = $file->getContents();

        preg_match_all("/@(?:can|cannot)\\s*\\(\\s*'([^']+)'\\s*(,|\\))/", $code, $single, PREG_SET_ORDER);
        preg_match_all('/@canany\\s*\\(\\s*\\[([^\\]]*)\\]\\s*(,|\\))/', $code, $any, PREG_SET_ORDER);

        $names = [];
        foreach ($single as [, $name, $next]) {
            if ($next === ')') {
                $names[] = $name;
            }
        }
        foreach ($any as [, $list, $next]) {
            if ($next === ')') {
                preg_match_all("/'([^']+)'/", $list, $quoted);
                array_push($names, ...$quoted[1]);
            }
        }

        foreach ($names as $name) {
            if (! isset($known[$name]) && ! Gate::has($name)) {
                $unknown[] = $file->getRelativePathname().": {$name}";
            }
        }
    }

    expect($unknown)->toBe([]);
});
