<?php

namespace Tests\Support;

use App\Models\Business;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

/**
 * The two-business test fixture (stage 1a): businesses A and B, each with
 * shops, people and records (BusinessFixture), plus a user who belongs to
 * both, with A as their default business.
 *
 * Both businesses use the same shop codes, so per-business keys are
 * exercised too. Leaves the current business as it found it.
 */
final class TwoBusinesses
{
    private function __construct(
        public readonly BusinessFixture $a,
        public readonly BusinessFixture $b,
        public readonly User $both,
    ) {}

    public static function create(): self
    {
        Role::findOrCreate('super-admin', 'web');
        Role::findOrCreate('cashier', 'web');

        $a = self::business('Business A', 'Alpha');
        $b = self::business('Business B', 'Bravo');

        $both = User::factory()->memberOf($a->business)->create(['name' => 'Member of Both']);
        $both->joinBusiness($b->business);
        $both->assignRole('cashier');
        $both->shops()->attach([$a->shops[0]->id, $b->shops[0]->id]);

        return new self($a, $b, $both);
    }

    private static function business(string $name, string $marker): BusinessFixture
    {
        $business = Business::factory()->create(['name' => $name]);

        return app(TenantContext::class)->run($business, function () use ($business, $marker): BusinessFixture {
            $shops = [
                Shop::factory()->create(['name' => "{$marker} Main", 'code' => 'MAIN']),
                Shop::factory()->create(['name' => "{$marker} Branch", 'code' => 'BRANCH']),
            ];

            $owner = User::factory()->memberOf($business)->create(['name' => "{$marker} Owner"]);
            $owner->assignRole('super-admin');

            $cashier = User::factory()->memberOf($business)->create(['name' => "{$marker} Cashier"]);
            $cashier->assignRole('cashier');
            $cashier->shops()->attach($shops[0]->id);

            return new BusinessFixture(
                business: $business,
                shops: $shops,
                owner: $owner,
                cashier: $cashier,
                customer: Customer::factory()->forShop($shops[0])->create(['name' => "{$marker} Customer", 'code' => 'CUST-1']),
                product: Product::factory()->create(['name' => "{$marker} Product", 'sku' => 'SKU-1', 'shop_id' => $shops[0]->id]),
                supplier: Supplier::factory()->create(['name' => "{$marker} Supplier", 'code' => 'SUP-1']),
                sale: Sale::factory()->create([
                    'shop_id' => $shops[1]->id,
                    'register_id' => CashRegister::factory()->create(['shop_id' => $shops[1]->id, 'user_id' => $owner->id])->id,
                    'invoice_number' => 'INV-1',
                ]),
            );
        });
    }
}
