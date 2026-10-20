<?php

use App\Http\Middleware\ResolveTenant;
use App\Support\Tenancy\BusinessLink;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\URL;
use Tests\Support\TwoBusinesses;

/**
 * A link in a notification belongs to the business it is about (stage 3):
 * opening it switches the reader's session to that business first.
 */
beforeEach(function () {
    seedRolesAndPermissions();
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a->business;
    $this->b = $this->fixture->b->business;
    $this->both = $this->fixture->both;
    $this->both->forceFill(['default_business_id' => $this->a->id])->save();
});

function linkFor($business, string $path): string
{
    return app(TenantContext::class)->run($business, fn () => BusinessLink::to($path));
}

it('switches the session to the notification\'s business, then opens its page', function () {
    $this->actingAs($this->both)->withSession([ResolveTenant::SESSION_KEY => $this->a->id]);

    $this->get(linkFor($this->b, '/customers'))->assertRedirect('/customers');

    expect(session(ResolveTenant::SESSION_KEY))->toBe($this->b->id);
});

it('is a 404 for someone who can\'t work in that business, and switches nothing', function () {
    $this->actingAs($this->fixture->a->owner)->withSession([ResolveTenant::SESSION_KEY => $this->a->id]);

    $this->get(linkFor($this->b, '/customers'))->assertNotFound();

    expect(session(ResolveTenant::SESSION_KEY))->toBe($this->a->id);
});

it('refuses a link that was changed, and one that points off the site', function () {
    $this->actingAs($this->both);

    $this->get(str_replace('customers', 'sales', linkFor($this->b, '/customers')))->assertForbidden();

    $offsite = URL::signedRoute('businesses.open', ['business' => $this->b->uuid, 'to' => '//evil.test/x']);
    $this->get($offsite)->assertNotFound();
});

it('falls back to a plain link when no business is set', function () {
    app(TenantContext::class)->forget();

    expect(BusinessLink::to('/customers'))->toBe(url('/customers'));
});
