<?php

use App\Models\Business;
use App\Models\User;
use App\Support\Navigation\Sidebar;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;

/**
 * The business sidebar (fix/notices-and-sidebar-ui): one definition, filtered
 * for the user. Every link goes to a page that lets the user in, a section
 * shows only when something under it does, nothing appears twice, and the
 * group holding the current page is open with that page marked.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->business = Business::factory()->create();
    $this->tenant = app(TenantContext::class);

    [$this->owner, $this->manager, $this->cashier] = $this->tenant->run($this->business, function (): array {
        $make = function (string $role): User {
            $user = User::factory()->memberOf($this->business)->create();
            $user->assignRole($role);
            assignToNewShops($user);

            return $user;
        };

        return [$make('admin'), $make('manager'), $make('cashier')];
    });
    $this->tenant->forget();
});

/**
 * Every link in $user's menu, as [label, route].
 *
 * @return list<array{0: string, 1: string}>
 */
function menuLinks(User $user, Business $business): array
{
    return app(TenantContext::class)->run($business, function () use ($user): array {
        $links = [];
        foreach (Sidebar::for($user) as $section) {
            foreach ($section['items'] as $item) {
                foreach ($item['children'] ?? [$item] as $link) {
                    $links[] = [$link['label'], $link['route']];
                }
            }
        }

        return $links;
    });
}

/**
 * The rendered sidebar of a page.
 */
function sidebarHtml(string $html): string
{
    $start = strpos($html, '<div class="main-nav">');

    return substr($html, $start, strpos($html, '<div class="page-content">') - $start);
}

it('links only to routes that exist, each once', function () {
    $routes = array_column(menuLinks($this->owner, $this->business), 1);

    foreach ($routes as $route) {
        expect(Route::has($route))->toBeTrue("{$route} doesn't exist");
    }
    expect($routes)->toBe(array_values(array_unique($routes)))
        ->and(count($routes))->toBeGreaterThan(30);
});

it('shows each person only pages that let them in', function (string $who) {
    foreach (menuLinks($this->{$who}, $this->business) as [$label, $route]) {
        $this->tenant->forget();
        $status = $this->actingAs($this->{$who})->get(route($route))->getStatusCode();

        expect($status)->toBe(200, "\"{$label}\" ({$route}) answered {$status} to the {$who}");
    }
})->with(['manager', 'cashier']);

it('hides what a cashier may not open, and the sections left empty', function () {
    $routes = array_column(menuLinks($this->cashier, $this->business), 1);
    $html = sidebarHtml($this->actingAs($this->cashier)->get(route('dashboard'))->assertOk()->getContent());

    expect($routes)->toContain('dashboard')->toContain('sales.index')
        ->not->toContain('roles.index')->not->toContain('users.index')->not->toContain('billing.show')
        ->not->toContain('business.settings.edit')->not->toContain('audit-logs.index')->not->toContain('stock-intakes.index')
        ->and($html)->not->toContain('>People<')->not->toContain('>Business<');
});

it('gives an owner every section', function () {
    $html = sidebarHtml($this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->getContent());

    foreach (['General', 'Catalogue', 'Stock', 'Sales', 'Finance', 'Marketing', 'Reports', 'People', 'Business'] as $title) {
        expect($html)->toContain('<li class="menu-title'.($title === 'General' ? ' ' : ' mt-2').'">'.$title.'</li>');
    }
});

it('repeats no element id and no section', function () {
    $html = sidebarHtml($this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->getContent());

    preg_match_all('/\sid="([^"]+)"/', $html, $ids);
    preg_match_all('/<li class="menu-title[^"]*">([^<]+)<\/li>/', $html, $titles);

    expect($ids[1])->toBe(array_values(array_unique($ids[1])))
        ->and($titles[1])->toBe(array_values(array_unique($titles[1])))
        ->and(substr_count($html, 'href="'.route('customers.index').'"'))->toBe(1)
        ->and(substr_count($html, 'href="'.route('users.index').'"'))->toBe(1);
});

it('opens the group holding the current page and marks the page', function () {
    $html = sidebarHtml($this->actingAs($this->manager)->get(route('stock-intakes.index'))->assertOk()->getContent());

    expect($html)->toContain('class="collapse show" id="sidebar-stock"')
        ->toContain('aria-controls="sidebar-stock"')
        ->toMatch('/class="sub-nav-link active" href="'.preg_quote(route('stock-intakes.index'), '/').'"\s+aria-current="page"/');
});
