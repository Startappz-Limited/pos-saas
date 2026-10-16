<?php

use App\Models\Business;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
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
| The test business
|--------------------------------------------------------------------------
|
| Every shop, product, list and customer belongs to a business (stage 1a). Each
| feature test runs inside one: records created without a business join it,
| and users created by the factory become its members with it as their
| default, so their requests act on it. Tests about several businesses build
| their own on top (tests/Support/TwoBusinesses.php).
|
*/

pest()->beforeEach(function () {
    app(TenantContext::class)->set(Business::factory()->create(['name' => 'Test Business']));
})->in('Feature');

function testBusiness(): Business
{
    return app(TenantContext::class)->business();
}

/**
 * A shared role template (business_id NULL) that every business can assign,
 * as the seeded roles are (Spatie teams, stage 1a). A role created while a
 * business is set belongs to that business only.
 */
function sharedRole(string $name): Role
{
    return Role::withoutGlobalScopes()->whereNull('business_id')->where('name', $name)->where('guard_name', 'web')->first()
        ?? Role::create(['name' => $name, 'guard_name' => 'web', 'business_id' => null]);
}

/**
 * Every system permission, as PermissionSeeder adds them (one query).
 */
function seedPermissions(): void
{
    PermissionSeeder::insertMissing();
}

/**
 * The permissions and the seeded role templates with theirs, as a real install
 * has them.
 */
function seedRolesAndPermissions(): void
{
    seedPermissions();
    test()->seed(RoleSeeder::class);
}

/**
 * The business owner role (decision 4.4, stage 3): the shared `admin` role,
 * holding every permission. Owners no longer pass every gate (only the platform
 * operator does), so the permissions are seeded when a test hasn't.
 */
function ownerRole(): Role
{
    seedPermissions();

    $role = sharedRole('admin');
    $role->syncPermissions(Permission::all());

    return $role;
}

/**
 * Shop access is default-deny (stage 1a): staff see only the shops assigned
 * to them. For tests about staff working in the shops the scenario creates,
 * this assigns $user to the business's shops and to every shop the test
 * creates from now on. Tests about shops the user may NOT access assign shops
 * explicitly instead.
 */
function assignToNewShops(User $user): User
{
    $user->shops()->syncWithoutDetaching(Shop::pluck('id')->all());
    Shop::created(fn (Shop $shop) => $user->shops()->syncWithoutDetaching([$shop->id]));

    return $user;
}

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

/**
 * Configure the WhatsApp Cloud API for the current test.
 *
 * phpunit.xml leaves it unconfigured, and a notification whose via() returns
 * no channels is never recorded by Notification::fake(), so any test that
 * expects a WhatsApp notification must call this first.
 */
function configureWhatsApp(): void
{
    config([
        'services.whatsapp.api_url' => 'https://api.example.com',
        'services.whatsapp.api_token' => 'test-token',
    ]);
}
