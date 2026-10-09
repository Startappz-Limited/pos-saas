<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Audit trail isolation
|--------------------------------------------------------------------------
|
| Audit rows are written on the separate `audit` connection, so they are NOT
| covered by RefreshDatabase's transaction and would otherwise accumulate for the
| whole suite — every model create/update in every test, each carrying a JSON
| snapshot of the model's attributes. Left unbounded that exhausts memory.
|
| Clearing it per test also keeps audit assertions deterministic.
|
*/

pest()->beforeEach(function () {
    if (Schema::connection('audit')->hasTable('audit_logs')) {
        DB::connection('audit')->table('audit_logs')->delete();
    }
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Shop access
|--------------------------------------------------------------------------
|
| Staff only reach the app once they are linked to a shop; otherwise they get
| the "contact your administrator" page (EnsureUserIsLinkedToShop). Use
| staffUser() for "logged in but lacks the permission" tests, so the test
| exercises the permission check rather than that redirect.
|
*/

function staffUser(?Shop $shop = null, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->shops()->attach($shop ?? Shop::factory()->create());

    return $user;
}
