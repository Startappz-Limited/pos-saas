<?php

use App\Models\BusinessInvite;
use App\Models\Role;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Blade;

/**
 * <x-ui-button> with an href is a link that goes there when clicked: it
 * renders <a class="btn" href="…">. It used to render a <button href="…">,
 * which browsers ignore, so every such button went nowhere. Checked on the
 * component and on every page that gives it a link.
 */

/**
 * The href of the button-styled link labelled $label on $html, or null.
 */
function linkButtonHref(string $html, string $label): ?string
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
    foreach ((new DOMXPath($dom))->query('//a[contains(concat(" ", normalize-space(@class), " "), " btn ")]') as $link) {
        if (trim(preg_replace('/\s+/', ' ', $link->textContent)) === $label) {
            return $link->getAttribute('href');
        }
    }

    return null;
}

/**
 * Buttons on $html that carry an href (and so go nowhere).
 */
function buttonsWithHref(string $html): int
{
    return preg_match_all('/<button\b[^>]*\shref=/i', $html);
}

describe('the component', function () {
    it('renders a link that goes to its href', function () {
        $html = Blade::render('<x-ui-button variant="secondary" size="sm" icon="solar:pen-2-broken" href="/users/create" class="w-100" data-test="x">Add User</x-ui-button>');

        expect(linkButtonHref($html, 'Add User'))->toBe('/users/create')
            ->and($html)->toContain('class="btn btn-secondary btn-sm w-100"')
            ->and($html)->toContain('data-test="x"')
            ->and($html)->toContain('icon="solar:pen-2-broken"')
            ->and(buttonsWithHref($html))->toBe(0)
            // No stray attributes from its options.
            ->and($html)->not->toContain('variant=')->not->toContain('size=')
            ->and(substr_count($html, 'class="btn'))->toBe(1);
    });

    it('stays a button without an href, with its type', function () {
        $html = Blade::render('<x-ui-button variant="danger" type="submit">Save</x-ui-button>');

        expect($html)->toContain('<button type="submit"')
            ->and($html)->toContain('class="btn btn-danger"')
            ->and($html)->not->toContain('<a ');
    });

    it('defaults to a primary button that submits nothing', function () {
        expect(Blade::render('<x-ui-button>Go</x-ui-button>'))->toContain('<button type="button"')->toContain('class="btn btn-primary"');
    });

    it('disables a button, and a link so it can\'t be followed', function () {
        expect(Blade::render('<x-ui-button :disabled="true">Save</x-ui-button>'))->toContain(' disabled');

        $link = Blade::render('<x-ui-button href="/users" :disabled="true">Users</x-ui-button>');
        expect(linkButtonHref($link, 'Users'))->toBe('#')
            ->and($link)->toContain('aria-disabled="true"')
            ->and($link)->toContain('btn-primary disabled');
    });

    it('keeps Bootstrap attributes for modal buttons', function () {
        $html = Blade::render('<x-ui-button variant="danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal">Remove</x-ui-button>');

        expect($html)->toContain('data-bs-toggle="modal"')->toContain('data-bs-target="#deleteUserModal"')->toContain('<button');
    });
});

describe('every page that gives it a link', function () {
    beforeEach(function () {
        seedRolesAndPermissions();
        $this->owner = User::factory()->create();
        $this->owner->assignRole(ownerRole());
        $this->tenant = app(TenantContext::class);
        $this->role = Role::create(['name' => 'stock taker', 'guard_name' => 'web']);
    });

    it('links each button to where it says, on the users and roles screens', function (string $page, Closure $url, array $links) {
        $this->tenant->forget();
        $html = $this->actingAs($this->owner)->get($url($this))->assertOk()->getContent();

        foreach ($links as $label => $target) {
            expect(linkButtonHref($html, $label))->toBe($target($this), "\"{$label}\" on {$page}");
        }
        expect(buttonsWithHref($html))->toBe(0);
    })->with([
        'users index' => ['users.index', fn ($t) => route('users.index'), ['Add User' => fn () => route('invites.index')]],
        'users edit' => ['users.edit', fn ($t) => route('users.edit', $t->owner), ['Cancel' => fn () => route('users.index')]],
        'users show' => ['users.show', fn ($t) => route('users.show', $t->owner), ['Edit Profile' => fn ($t) => route('users.edit', $t->owner)]],
        'roles index' => ['roles.index', fn ($t) => route('roles.index'), ['Add Role' => fn () => route('roles.create')]],
        'roles create' => ['roles.create', fn ($t) => route('roles.create'), ['Cancel' => fn () => route('roles.index')]],
        'roles edit' => ['roles.edit', fn ($t) => route('roles.edit', $t->role), ['Cancel' => fn () => route('roles.index')]],
        'roles show' => ['roles.show', fn ($t) => route('roles.show', $t->role), ['Edit Role' => fn ($t) => route('roles.edit', $t->role)]],
    ]);

    it('follows the link to a page that opens', function () {
        $this->tenant->forget();
        $html = $this->actingAs($this->owner)->get(route('users.index'))->getContent();

        $this->tenant->forget();
        // The invite page (adding a user is one invite flow since the signup stage), by its own title:
        // the word "Create" this checked for came from the old sidebar.
        expect(linkButtonHref($html, 'Add User'))->toBe(route('invites.index'));
        $this->actingAs($this->owner)->get(linkButtonHref($html, 'Add User'))->assertOk()->assertSee('<title>Add user</title>', false);
    });

    it('links the invitation pages to sign-in', function () {
        $existing = User::factory()->create(['email' => 'invited@example.test']);
        $token = str_repeat('a', 40);
        $this->tenant->run($this->tenant->business(), fn () => BusinessInvite::factory()->create([
            'email' => $existing->email, 'token_hash' => BusinessInvite::hashToken($token),
        ]));
        $this->tenant->forget();

        $show = $this->get(route('invitations.show', $token))->assertOk()->getContent();
        $invalid = $this->get(route('invitations.show', 'no-such-token'))->assertOk()->getContent();

        expect(linkButtonHref($show, 'Sign in'))->toBe(route('invitations.sign-in', $token))
            ->and(linkButtonHref($invalid, 'Go to sign in'))->toBe(route('login'))
            ->and(buttonsWithHref($show) + buttonsWithHref($invalid))->toBe(0);
    });
});
