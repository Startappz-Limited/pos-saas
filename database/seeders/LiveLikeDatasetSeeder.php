<?php

namespace Database\Seeders;

use App\Actions\ConvertAbandonedCartToSale;
use App\Actions\ConvertEcommerceOrderToSale;
use App\Http\Controllers\Api\SaleController as ApiSaleController;
use App\Http\Controllers\SaleController as WebSaleController;
use App\Http\Requests\StoreSaleRequest;
use App\Models\AbandonedCart;
use App\Models\AbandonedCartItem;
use App\Models\Attribute;
use App\Models\Business;
use App\Models\Category;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\DeliveryCompany;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Refund;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\StockIntake;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Integration\ShopifyOrderSyncService;
use App\Services\Integration\WooCommerceOrderSyncService;
use App\Services\StockAdjustmentService;
use App\Support\Dataset\Determinism;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Seeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Response;

/**
 * The live-like dataset (stage 0): a system that looks like it has been in use
 * for a year, so development, migrations and imports are rehearsed against
 * realistic data rather than an empty database.
 *
 * Reproducible: a fixed seed, a fixed clock and seeded UUIDs/random strings
 * (Determinism) give the same rows on every run at the same scale.
 *
 * Sales go through the real code paths the system uses: the web and API sale
 * controllers' store(), and the e-commerce order and abandoned cart
 * converters. Everything else uses factories or the real services.
 *
 * Local and testing environments only; built by `php artisan dataset:build`.
 */
class LiveLikeDatasetSeeder extends Seeder
{
    public const SEED = 20261013;

    public const STARTS = '2025-10-01 08:00:00';

    /**
     * Tables the build summary prints.
     *
     * @var list<string>
     */
    public const SUMMARY_TABLES = [
        'shops', 'users', 'products', 'product_variations', 'shop_product', 'customers', 'credit_accounts',
        'suppliers', 'purchase_orders', 'stock_intakes', 'stock_adjustments', 'stock_movements', 'sales',
        'sale_items', 'sale_payments', 'returns', 'refunds', 'credit_transactions', 'expenses',
        'ecommerce_orders', 'abandoned_carts', 'cash_registers',
    ];

    /**
     * Fraction of the medium volume (5 shops, ~25 staff, 400 products,
     * 500 customers, 3,000 sales, 300 expenses).
     */
    public float $scale = 1.0;

    /** @var Collection<int, Shop> */
    private Collection $shops;

    private User $owner;

    /** @var array<int, Collection<int, User>> cashiers by shop id */
    private array $cashiers = [];

    /** @var array<int, User> manager by shop id */
    private array $managers = [];

    /** @var array<string, SaleSource> */
    private array $sources = [];

    /** @var array<int, Collection<int, Product>> sellable products by shop id */
    private array $catalogue = [];

    /** @var array<int, Collection<int, Customer>> customers by shop id */
    private array $customers = [];

    public Business $business;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The live-like dataset is only built in the local or testing environment.');
        }

        $this->ensureCommand();
        Determinism::start(self::SEED, self::STARTS);
        $this->fakeEcommercePlatforms();
        $tenant = app(TenantContext::class);
        $previousBusiness = $tenant->business();

        try {
            // One transaction: committing every insert separately is very slow
            // on some local MySQL setups. The code paths' own transactions nest
            // inside it as savepoints, so they behave as in production.
            DB::transaction(function (): void {
                $this->call([PermissionSeeder::class, RoleSeeder::class]);

                // Everything below belongs to one business (stage 1a). Created
                // without Faker so the dataset's random draws stay in order.
                $this->business = Business::create(['name' => 'Live-like Retail', 'currency' => 'KES', 'timezone' => 'Africa/Nairobi']);
                app(TenantContext::class)->set($this->business);

                $this->people();
                $this->shops();
                $this->sharedLists();
                $this->catalogue();
                $this->customers();
                $this->purchasing();
                $this->stockActivity();
                $this->sales();
                $this->creditPayments();
                $this->returnsAndRefunds();
                $this->expenses();
                $this->openOnlineOrdersAndCarts();
                $this->softDeletions();
            });
        } finally {
            $tenant->set($previousBusiness);
            Auth::forgetUser();
            Determinism::stop();
        }
    }

    // --- people and shops ----------------------------------------------------

    private function people(): void
    {
        $this->owner = $this->user('owner@dataset.test', 'Owner Account', 'super-admin');
    }

    private function shops(): void
    {
        $branches = [
            ['Westlands', 'WST'], ['CBD', 'CBD'], ['Kilimani', 'KLM'], ['Mombasa Road', 'MBR'], ['Thika Road', 'THK'],
        ];

        $this->shops = collect($branches)->map(fn (array $branch): Shop => Shop::factory()->create([
            'name' => "{$branch[0]} Branch",
            'code' => $branch[1],
            'manager_id' => $this->owner->id,
            'status' => 'active',
            'country' => 'Kenya',
        ]));

        foreach ($this->shops as $index => $shop) {
            $manager = $this->user("manager.{$shop->code}@dataset.test", "{$shop->name} Manager", 'manager', [$shop]);
            $this->managers[$shop->id] = $manager;
            $shop->update(['manager_id' => $manager->id]);

            $this->cashiers[$shop->id] = collect([
                $this->user("cashier1.{$shop->code}@dataset.test", fake()->name(), 'cashier', [$shop]),
                $this->user("cashier2.{$shop->code}@dataset.test", fake()->name(), 'cashier', [$shop]),
            ]);
        }

        // Staff in two shops, staff in none, and someone no longer working.
        $floater = $this->user('cashier.floater@dataset.test', fake()->name(), 'cashier', [$this->shops[0], $this->shops[1]]);
        $this->cashiers[$this->shops[0]->id]->push($floater);
        $this->cashiers[$this->shops[1]->id]->push($floater);

        foreach (range(1, 4) as $i) {
            $this->user("clerk{$i}@dataset.test", fake()->name(), 'inventory-clerk', [$this->shops[$i % 5]]);
        }
        $this->user('accountant1@dataset.test', fake()->name(), 'accountant');
        $this->user('accountant2@dataset.test', fake()->name(), 'accountant');
        $this->user('former.cashier@dataset.test', fake()->name(), 'cashier', [$this->shops[2]], 'inactive');
    }

    /**
     * @param  list<Shop>  $shops
     */
    private function user(string $email, string $name, string $role, array $shops = [], string $status = 'active'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => Determinism::PASSWORD_HASH,
            'status' => $status,
        ]);
        $user->assignRole($role);
        $user->shops()->attach(collect($shops)->pluck('id')->all());

        return $user;
    }

    // --- shared lists and catalogue -------------------------------------------

    private function sharedLists(): void
    {
        foreach (['Walk-in', 'Online Shop', 'Phone Order', 'WhatsApp', 'Instagram'] as $order => $name) {
            // A new business already has some sources (Walk-in); reuse them.
            $this->sources[$name] = SaleSource::firstOrCreate(['business_id' => $this->business->id, 'name' => $name], ['is_active' => true, 'sort_order' => $order + 1]);
        }
        SaleSource::factory()->inactive()->create(['name' => 'Market Stall (closed)']);

        DeliveryCompany::factory()->count(3)->create();
        foreach (['Size' => ['S', 'M', 'L', 'XL'], 'Colour' => ['Black', 'Blue', 'Red'], 'Weight' => ['1kg', '2kg', '5kg'], 'Flavour' => ['Vanilla', 'Chocolate', 'Strawberry']] as $name => $values) {
            Attribute::factory()->create(['name' => $name, 'slug' => Str::slug($name), 'values' => $values]);
        }
        ExpenseCategory::factory()->count(8)->create();

        foreach (['Supplements', 'Protein', 'Equipment', 'Weights', 'Apparel', 'Footwear', 'Accessories', 'Yoga', 'Cardio', 'Recovery', 'Nutrition Bars', 'Hydration'] as $name) {
            Category::factory()->create(['name' => $name, 'slug' => Str::slug($name)]);
        }

        Supplier::factory()->count($this->count(13))->create();
        // Duplicate-looking suppliers, as a real supplier list accumulates.
        Supplier::factory()->create(['name' => 'Acme Supplies', 'email' => 'orders@acme.test']);
        Supplier::factory()->create(['name' => 'ACME Supplies Ltd', 'email' => 'sales@acme.test']);
    }

    private function catalogue(): void
    {
        $categories = Category::pluck('id');
        $suppliers = Supplier::pluck('id');
        $names = ['Protein Shake', 'Yoga Mat', 'Resistance Band', 'Water Bottle', 'Gym Gloves', 'Skipping Rope', 'Dumbbell Set', 'Energy Bar'];

        foreach (range(1, $this->count(400)) as $i) {
            $shop = $this->shops[$i % $this->shops->count()];
            $hasVariations = $i % 3 === 0;
            // The same product names recur across shops with different SKUs.
            $name = $names[$i % count($names)].' '.fake()->randomElement(['Classic', 'Pro', 'Lite', 'Max']).' '.(intdiv($i, 8) + 1);

            $product = Product::factory()->create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.$i,
                'sku' => sprintf('SKU-%s-%04d', $shop->code, $i),
                'category_id' => $categories[$i % $categories->count()],
                'supplier_id' => $suppliers[$i % $suppliers->count()],
                'shop_id' => $shop->id,
                'has_variations' => $hasVariations,
                'track_stock' => true,
                'stock_quantity' => fake()->numberBetween(400, 1500),
                'reorder_level' => fake()->numberBetween(10, 30),
                'status' => 'active',
            ]);

            if ($hasVariations) {
                ProductVariation::factory()->count(fake()->numberBetween(2, 4))->create([
                    'product_id' => $product->id,
                    'stock_quantity' => fake()->numberBetween(150, 500),
                ]);
            }

            if ($i % 5 !== 0) {
                $product->shops()->attach($shop->id, ['stock_quantity' => fake()->numberBetween(50, 400), 'reorder_level' => 10]);
            }
            if ($i % 7 === 0) {
                $other = $this->shops[($i + 2) % $this->shops->count()];
                $product->shops()->syncWithoutDetaching([$other->id => ['stock_quantity' => fake()->numberBetween(20, 200), 'reorder_level' => 10]]);
            }

            $this->catalogue[$shop->id] = ($this->catalogue[$shop->id] ?? collect())->push($product);
        }

        PricingRule::factory()->count(5)->create([
            'product_id' => fn () => $this->pick(Product::orderBy('id')->pluck('id')),
            'customer_id' => null,
        ]);
    }

    // --- customers and credit ----------------------------------------------------

    private function customers(): void
    {
        $perShop = max(2, intdiv($this->count(500), $this->shops->count()));

        foreach ($this->shops as $shop) {
            $wholesale = Customer::factory()->forShop($shop)->wholesale()->withCredit()
                ->count(max(1, intdiv($perShop, 5)))
                ->create(['created_by' => $this->managers[$shop->id]->id]);
            $retail = Customer::factory()->forShop($shop)
                ->count($perShop - $wholesale->count())
                ->create(['customer_type' => 'retail', 'allow_credit' => false, 'created_by' => $this->managers[$shop->id]->id]);

            foreach ($wholesale as $customer) {
                CreditAccount::factory()->create([
                    'customer_id' => $customer->id,
                    'shop_id' => $shop->id,
                    'credit_limit' => $customer->credit_limit,
                    'current_balance' => 0,
                    'available_credit' => $customer->credit_limit,
                    'status' => 'active',
                    'is_suspended' => false,
                ]);
            }

            $this->customers[$shop->id] = $wholesale->concat($retail)->values();
        }

        // The same person registered in two shops (same name and phone).
        foreach ([[0, 1], [2, 3], [1, 4]] as [$a, $b]) {
            $template = $this->customers[$this->shops[$a]->id]->last();
            $twin = Customer::factory()->forShop($this->shops[$b])->create([
                'name' => $template->name,
                'phone' => $template->phone,
                'customer_type' => 'retail',
                'allow_credit' => false,
            ]);
            $this->customers[$this->shops[$b]->id]->push($twin);
        }
    }

    // --- purchasing and stock -----------------------------------------------------

    private function purchasing(): void
    {
        foreach (range(1, $this->count(40)) as $i) {
            $shop = $this->shops[$i % $this->shops->count()];
            $order = PurchaseOrder::factory()->create([
                'shop_id' => $shop->id,
                'supplier_id' => $this->pick(Supplier::orderBy('id')->pluck('id')),
                'created_by' => $this->managers[$shop->id]->id,
                'approved_by' => $this->owner->id,
            ]);

            // Not PurchaseOrderItem::factory(): its definition always creates a
            // new product of its own, even when product_id is given.
            foreach ($this->pickMany($this->catalogue[$shop->id], 3) as $product) {
                $quantity = fake()->numberBetween(10, 100);
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'product_name' => $product->name,
                    'quantity_ordered' => $quantity,
                    'quantity_received' => 0,
                    'quantity_remaining' => $quantity,
                    'unit' => 'pcs',
                    'unit_cost' => (float) ($product->cost_price ?? 0),
                    'tax_rate' => 0,
                    'discount_percent' => 0,
                ]);
            }
        }

        foreach (range(1, $this->count(10)) as $i) {
            $shop = $this->shops[$i % $this->shops->count()];
            $return = PurchaseReturn::factory()->create([
                'shop_id' => $shop->id,
                'supplier_id' => $this->pick(Supplier::orderBy('id')->pluck('id')),
                'requested_by' => $this->managers[$shop->id]->id,
                'approved_by' => $this->owner->id,
            ]);
            PurchaseReturnItem::factory()->create([
                'purchase_return_id' => $return->id,
                'product_id' => $this->pick($this->catalogue[$shop->id])->id,
            ]);
        }
    }

    private function stockActivity(): void
    {
        foreach (range(1, $this->count(60)) as $i) {
            $shop = $this->shops[$i % $this->shops->count()];
            Determinism::travelTo(Carbon::parse(self::STARTS)->addDays($i * 5));
            StockIntake::factory()->create([
                'shop_id' => $shop->id,
                'product_id' => $this->pick($this->catalogue[$shop->id])->id,
                'supplier_id' => $this->pick(Supplier::orderBy('id')->pluck('id')),
                'purchase_order_id' => null,
                'purchase_order_item_id' => null,
                'received_by' => $this->managers[$shop->id]->id,
                'completed_by' => $this->managers[$shop->id]->id,
            ]);
        }

        $adjustments = app(StockAdjustmentService::class);
        foreach (range(1, $this->count(40)) as $i) {
            $shop = $this->shops[$i % $this->shops->count()];
            $increase = $i % 4 === 0;
            Determinism::travelTo(Carbon::parse(self::STARTS)->addDays($i * 8));
            $adjustment = $adjustments->createAdjustment([
                'shop_id' => $shop->id,
                'type' => $increase ? 'increase' : 'decrease',
                'reason' => $increase ? 'found' : fake()->randomElement(['damaged', 'expired', 'lost', 'recount']),
                'notes' => fake()->optional()->sentence(),
                'items' => [[
                    'product_id' => $this->pick($this->catalogue[$shop->id]->where('has_variations', false))->id,
                    'quantity_change' => $increase ? fake()->numberBetween(1, 10) : -fake()->numberBetween(1, 5),
                ]],
            ], $this->managers[$shop->id]->id);

            if ($i % 5 !== 0) {
                $adjustments->approveAdjustment($adjustment, $this->owner->id);
            }
        }

        foreach (range(1, $this->count(200)) as $i) {
            $shop = $this->shops[$i % $this->shops->count()];
            StockMovement::factory()->create([
                'shop_id' => $shop->id,
                'product_id' => $this->pick($this->catalogue[$shop->id])->id,
                'variation_id' => null,
                'created_by' => $this->managers[$shop->id]->id,
            ]);
        }
    }

    // --- sales through the real paths ---------------------------------------------

    private function sales(): void
    {
        $total = $this->count(3000);
        $paths = ['web', 'api'];

        for ($n = 0; $n < $total; $n++) {
            $moment = Carbon::parse(self::STARTS)->addMinutes((int) round($n * (365 * 24 * 60) / $total));
            if ($moment->hour < 8) {
                $moment->setTime(8 + $n % 10, $n % 60);
            }
            Determinism::travelTo($moment);

            $shop = $this->shops[$n % $this->shops->count()];
            $roll = $n % 50;

            match (true) {
                $roll === 7 => $this->convertEcommerceOrder($shop),
                $roll === 23 => $this->convertAbandonedCart($shop),
                default => $this->tillSale($paths[$n % 2], $shop),
            };
        }
    }

    private function tillSale(string $path, Shop $shop): Sale
    {
        $cashier = $this->pick($this->cashiers[$shop->id]);
        $customer = fake()->boolean(70) ? $this->pick($this->customers[$shop->id]) : null;
        $credit = $customer?->customer_type === 'wholesale' && $customer->allow_credit && fake()->boolean(40);

        $payload = [
            'shop_id' => $shop->id,
            'source_id' => $this->sources[fake()->randomElement(['Walk-in', 'Walk-in', 'Walk-in', 'Phone Order', 'WhatsApp', 'Instagram'])]->id,
            'delivery_location' => fake()->randomElement(['In store', 'In store', 'In store', fake()->streetAddress()]),
            'payment_method' => $credit ? 'credit' : fake()->randomElement(['cash', 'cash', 'mobile_money', 'mobile_money', 'card', 'bank_transfer']),
            'items' => $this->saleLines($shop),
            'discount_amount' => fake()->boolean(15) ? fake()->randomElement([50, 100, 200]) : 0,
        ];

        // A discount may not exceed the subtotal (StoreSaleRequest), and a flat
        // one can on a cheap basket. Capped after the draws, so the random
        // sequence (and the data the API contract was recorded on) is unchanged.
        $subtotal = collect($payload['items'])->sum(fn (array $line): float => $line['price'] * $line['quantity']);
        if ($payload['discount_amount'] >= $subtotal) {
            $payload['discount_amount'] = 0;
        }

        if ($customer) {
            $payload['customer_id'] = $customer->id;
        } else {
            $payload['walk_in_customer_name'] = fake()->name();
            $payload['walk_in_customer_phone'] = '07'.fake()->numerify('########');
        }

        return $this->postSale($path, $cashier, $payload);
    }

    /**
     * @return list<array{product_id: int, variation_id?: int, quantity: int, price: float}>
     */
    private function saleLines(Shop $shop): array
    {
        return $this->pickMany($this->catalogue[$shop->id], fake()->numberBetween(1, 4))
            ->map(function (Product $product): array {
                $variation = $product->has_variations ? $this->pick($product->variations()->orderBy('id')->get()) : null;
                $line = [
                    'product_id' => $product->id,
                    'quantity' => fake()->numberBetween(1, 3),
                    'price' => (float) ($variation?->selling_price ?? $product->selling_price),
                ];
                if ($variation) {
                    $line['variation_id'] = $variation->id;
                }

                return $line;
            })
            ->values()
            ->all();
    }

    /**
     * Posts a sale through the web or API controller's store(), with a real
     * StoreSaleRequest validated for the signed-in cashier.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postSale(string $path, User $cashier, array $payload): Sale
    {
        Auth::setUser($cashier);

        $request = StoreSaleRequest::create($path === 'web' ? '/sales' : '/api/sales', 'POST', $payload, server: [
            'HTTP_ACCEPT' => $path === 'web' ? 'text/html' : 'application/json',
        ]);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->setUserResolver(fn () => $cashier);
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);
        $request->validateResolved();

        $lastId = (int) Sale::max('id');
        $response = $path === 'web'
            ? app(WebSaleController::class)->store($request)
            : app(ApiSaleController::class)->store($request);

        $sale = Sale::where('id', '>', $lastId)->orderByDesc('id')->first();

        if (! $sale) {
            throw new RuntimeException("A {$path} sale failed: ".$this->describe($response));
        }

        return $sale;
    }

    private function describe(mixed $response): string
    {
        if ($response instanceof JsonResponse) {
            return $response->getContent();
        }

        $session = app('session.store');

        return $response instanceof Response
            ? 'HTTP '.$response->getStatusCode().' '.json_encode($session->get('errors')?->getMessages() ?? $session->get('error'))
            : get_debug_type($response);
    }

    private function convertEcommerceOrder(Shop $shop): Sale
    {
        $products = $this->pickMany($this->catalogue[$shop->id]->where('has_variations', false), fake()->numberBetween(1, 2));
        $order = EcommerceOrder::factory()->woocommerce()->processing()->create([
            'shop_id' => $shop->id,
            'platform_created_at' => $this->recentPast(),
        ]);
        $items = $products->map(fn (Product $product): array => [
            'product_id' => $product->id,
            'quantity' => fake()->numberBetween(1, 2),
            'unit_price' => (float) $product->selling_price,
        ])->values()->all();

        foreach ($items as $item) {
            EcommerceOrderItem::factory()->create([
                'ecommerce_order_id' => $order->id,
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['quantity'] * $item['unit_price'],
                'total' => $item['quantity'] * $item['unit_price'],
            ]);
        }

        Auth::setUser($this->managers[$shop->id]);

        return app(ConvertEcommerceOrderToSale::class)->execute($order, [
            'source_id' => $this->sources['Online Shop']->id,
            'delivery_location' => fake()->streetAddress().', '.fake()->randomElement(['Nairobi', 'Thika', 'Kiambu']),
            'payment_method' => 'mobile_money',
            'items' => $items,
        ], $this->managers[$shop->id]);
    }

    private function convertAbandonedCart(Shop $shop): Sale
    {
        $product = $this->pick($this->catalogue[$shop->id]->where('has_variations', false));
        $cart = AbandonedCart::factory()->create(['shop_id' => $shop->id, 'status' => 'abandoned']);
        AbandonedCartItem::factory()->create([
            'abandoned_cart_id' => $cart->id,
            'product_id' => $product->id,
            'variation_id' => null,
            'quantity' => 1,
            'unit_price' => $product->selling_price,
            'line_total' => $product->selling_price,
        ]);

        Auth::setUser($this->managers[$shop->id]);

        return app(ConvertAbandonedCartToSale::class)->execute($cart, [
            'source_id' => $this->sources['WhatsApp']->id,
            'payment_method' => 'mobile_money',
        ], $this->managers[$shop->id]);
    }

    /**
     * Collects payments against about half of the credit sales through the web
     * controller's collectPayment(), which settles the credit account.
     */
    private function creditPayments(): void
    {
        Auth::setUser($this->owner);

        Sale::where('payment_method', 'credit')->where('payment_status', '!=', 'paid')->orderBy('id')->get()
            ->filter(fn (Sale $sale, int $index): bool => $index % 2 === 0)
            ->each(function (Sale $sale): void {
                Determinism::travelTo($sale->completed_at->copy()->addDays(fake()->numberBetween(3, 40)));
                $amount = fake()->boolean(60) ? (float) $sale->balance_due : round((float) $sale->balance_due / 2, 2);

                $request = Request::create("/sales/{$sale->uuid}/payments", 'POST', [
                    'payments' => [['amount' => $amount, 'payment_method' => fake()->randomElement(['cash', 'mobile_money'])]],
                ], server: ['HTTP_ACCEPT' => 'application/json']);
                $request->setUserResolver(fn () => $this->owner);
                app()->instance('request', $request);

                $response = app(WebSaleController::class)->collectPayment($request, $sale);

                if ($response->getStatusCode() >= 400) {
                    throw new RuntimeException("Collecting a credit payment failed: {$response->getContent()}");
                }
            });
    }

    private function returnsAndRefunds(): void
    {
        Sale::with('items')->orderBy('id')->get()
            ->filter(fn (Sale $sale, int $index): bool => $index % 50 === 11)
            ->values()
            ->each(function (Sale $sale, int $index): void {
                $item = $sale->items->first();
                $return = SaleReturn::factory()->create([
                    'sale_id' => $sale->id,
                    'customer_id' => $sale->customer_id,
                    'shop_id' => $sale->shop_id,
                    'requested_by' => $this->managers[$sale->shop_id]->id,
                    'approved_by' => $this->owner->id,
                    'inspected_by' => $this->managers[$sale->shop_id]->id,
                ]);
                ReturnItem::factory()->create([
                    'return_id' => $return->id,
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                ]);

                if ($index % 2 === 0) {
                    Refund::factory()->create([
                        'return_id' => $return->id,
                        'customer_id' => $sale->customer_id,
                        'shop_id' => $sale->shop_id,
                        'processed_by' => $this->owner->id,
                    ]);
                }
            });
    }

    private function expenses(): void
    {
        $categories = ExpenseCategory::pluck('id');

        foreach (range(1, $this->count(300)) as $i) {
            $shop = $this->shops[$i % $this->shops->count()];
            Determinism::travelTo(Carbon::parse(self::STARTS)->addHours($i * 29));
            $expense = Expense::factory()->create([
                'shop_id' => $shop->id,
                'category_id' => $categories[$i % $categories->count()],
                'created_by' => $this->managers[$shop->id]->id,
            ]);

            if ($i % 10 === 0) {
                DB::table('expense_approval_history')->insert([
                    'uuid' => (string) Str::uuid(),
                    'expense_id' => $expense->id,
                    'user_id' => $this->owner->id,
                    'action' => 'approved',
                    'from_status' => 'pending',
                    'to_status' => 'approved',
                    'comments' => fake()->optional()->sentence(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        foreach ($this->shops as $shop) {
            foreach (['cash', 'mobile_money', 'card'] as $method) {
                DB::table('shop_payment_methods')->insert([
                    'uuid' => (string) Str::uuid(),
                    'shop_id' => $shop->id,
                    'payment_method' => $method,
                    'is_enabled' => true,
                    'created_by' => $this->owner->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Online orders and carts that haven't (yet) become sales.
     */
    private function openOnlineOrdersAndCarts(): void
    {
        foreach ($this->shops as $index => $shop) {
            EcommerceOrder::factory()->count(max(1, $this->count(20)))->create([
                'shop_id' => $shop->id,
                'platform_created_at' => fn () => $this->recentPast(),
            ]);
            AbandonedCart::factory()->count(max(1, $this->count(15)))->create(['shop_id' => $shop->id]);
        }
    }

    /**
     * Soft-deleted rows in the soft-deleting models, as in a system in use.
     */
    private function softDeletions(): void
    {
        Product::orderBy('id')->get()->filter(fn ($p, int $i): bool => $i % 40 === 3)->each->delete();
        Customer::orderBy('id')->get()->filter(fn ($c, int $i): bool => $i % 35 === 5)->each->delete();
        Supplier::where('name', 'ACME Supplies Ltd')->first()?->delete();
        Category::orderBy('id')->first()?->delete();
        Expense::orderBy('id')->get()->filter(fn ($e, int $i): bool => $i % 60 === 7)->each->delete();
        EcommerceOrder::whereNull('sale_id')->orderBy('id')->limit(3)->get()->each->delete();
        AbandonedCart::whereNull('sale_id')->orderBy('id')->limit(4)->get()->each->delete();
        // Leave at least one live row in each table, at any scale.
        PurchaseOrder::orderBy('id')->limit(min(2, PurchaseOrder::count() - 1))->get()->each->delete();
        if (StockIntake::count() > 1) {
            StockIntake::orderBy('id')->first()->delete();
        }

        $closed = Shop::factory()->create(['name' => 'Closed Kiosk', 'code' => 'KSK', 'manager_id' => $this->owner->id]);
        $closed->delete();
    }

    // --- helpers -------------------------------------------------------------

    /**
     * The permission and role seeders write progress to a command; when the
     * dataset is seeded from code (a test) rather than an artisan command,
     * give them a silent one.
     */
    private function ensureCommand(): void
    {
        if (isset($this->command)) {
            return;
        }

        $command = new class extends Command
        {
            protected $name = 'dataset:seed-quietly';
        };
        $command->setLaravel(app());
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

        $this->setContainer(app())->setCommand($command);
    }

    /**
     * A seeded random pick. Collection::random() uses random_int() and
     * inRandomOrder() uses the database's RAND(); neither can be seeded.
     *
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @return T
     */
    private function pick(Collection $items): mixed
    {
        $items = $items->values();

        return $items[mt_rand(0, $items->count() - 1)];
    }

    /**
     * Up to $count distinct items, picked with the seeded generator.
     *
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @return Collection<int, T>
     */
    private function pickMany(Collection $items, int $count): Collection
    {
        $pool = $items->values()->all();

        for ($i = count($pool) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
        }

        return collect(array_slice($pool, 0, min($count, count($pool))));
    }

    /**
     * A moment in the 30 days before the frozen clock. (Faker's
     * dateTimeBetween() reads the real clock, so it isn't reproducible.)
     */
    private function recentPast(): Carbon
    {
        return now()->subMinutes(fake()->numberBetween(30, 30 * 24 * 60));
    }

    private function count(int $medium): int
    {
        return max(1, (int) round($medium * $this->scale));
    }

    /**
     * The converters sync item changes back to WooCommerce/Shopify before
     * converting. The dataset has no real store to talk to, so the sync
     * reports success; the conversion itself runs the real code.
     */
    private function fakeEcommercePlatforms(): void
    {
        app()->instance(WooCommerceOrderSyncService::class, new class extends WooCommerceOrderSyncService
        {
            public function syncOrderItems(Shop $shop, EcommerceOrder $order, array $items, ?float $shippingTotal = null): array
            {
                return ['success' => true];
            }
        });
        app()->instance(ShopifyOrderSyncService::class, new class extends ShopifyOrderSyncService
        {
            public function syncOrderItems(Shop $shop, EcommerceOrder $order, array $items, ?float $shippingTotal = null): array
            {
                return ['success' => true];
            }
        });
    }
}
