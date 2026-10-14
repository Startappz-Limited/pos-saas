<?php

namespace Tests\Support;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;

/**
 * One business of the two-business fixture: two shops, an owner who sees
 * both, a cashier assigned to the first, and a record of each kind.
 */
final class BusinessFixture
{
    /**
     * @param  array{0: Shop, 1: Shop}  $shops
     */
    public function __construct(
        public readonly Business $business,
        public readonly array $shops,
        public readonly User $owner,
        public readonly User $cashier,
        public readonly Customer $customer,
        public readonly Product $product,
        public readonly Supplier $supplier,
        public readonly Sale $sale,
    ) {}
}
