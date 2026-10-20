<?php

namespace Database\Seeders;

use App\Enums\ExpenseCategoryType;
use App\Enums\StockMovementType;
use App\Http\Controllers\Api\SaleController as ApiSaleController;
use App\Http\Controllers\SaleController as WebSaleController;
use App\Http\Requests\StoreSaleRequest;
use App\Models\Business;
use App\Models\Category;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\DeliveryCompany;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Refund;
use App\Models\ReturnItem;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Stock\StockLedger;
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
 * Presentation data: one believable Nairobi retailer ("Kijani Retail") with
 * three branches and the last 90 days of trading that grows week on week.
 *
 * Unlike LiveLikeDatasetSeeder (volume and edge cases for rehearsals), this is
 * a story: real product names and KES prices, named customers, wholesale
 * accounts with credit that is partly paid and partly overdue, a few items
 * running low, expenses, purchase orders and a handful of returns.
 *
 * Sales go through the real web and API controllers' store(), so totals,
 * invoice numbers, FIFO costs and stock are exactly what the till produces.
 *
 * Local and testing environments only; built by `php artisan dataset:build --demo`
 * into an empty _testing database. Every account's password is "password".
 */
class DemoSeeder extends Seeder
{
    public const SEED = 20261016;

    /** Days of trading history, ending today. */
    public const DAYS = 90;

    /**
     * Tables the build summary prints.
     *
     * @var list<string>
     */
    public const SUMMARY_TABLES = [
        'shops', 'users', 'products', 'customers', 'credit_accounts', 'suppliers', 'purchase_orders',
        'stock_movements', 'sales', 'sale_items', 'sale_payments', 'returns', 'refunds', 'credit_transactions', 'expenses',
    ];

    /** Scales the number of sales per day. */
    public float $scale = 1.0;

    public Business $business;

    private User $owner;

    /** @var Collection<int, Shop> */
    private Collection $shops;

    /** @var array<int, User> */
    private array $managers = [];

    /** @var array<int, Collection<int, User>> */
    private array $cashiers = [];

    /** @var array<string, SaleSource> */
    private array $sources = [];

    /** @var array<int, Collection<int, Product>> the catalogue by shop id, in CATALOGUE order */
    private array $products = [];

    /** @var array<int, Collection<int, Customer>> retail and walk-in-style regulars by shop id */
    private array $regulars = [];

    /** @var array<int, Collection<int, Customer>> wholesale credit customers by shop id */
    private array $wholesale = [];

    private Carbon $start;

    /** @var array<string, mixed> the business being built, from profiles() */
    private array $profile = [];

    private int $profileIndex = 0;

    /**
     * Branch name, code, rent per month, city/address.
     *
     * @var list<array{0: string, 1: string, 2: int, 3: string}>
     */
    private const KIJANI_BRANCHES = [
        ['Westlands', 'WST', 85000, 'Woodvale Grove, Westlands'],
        ['CBD', 'CBD', 120000, 'Moi Avenue, Nairobi CBD'],
        ['Mombasa Road', 'MBR', 60000, 'Mombasa Road, Syokimau'],
    ];

    /**
     * Category, name, cost, price, unit, popularity weight.
     *
     * @var list<array{0: string, 1: string, 2: int, 3: int, 4: string, 5: int}>
     */
    private const KIJANI_CATALOGUE = [
        ['Beverages', 'Coca-Cola 500ml', 38, 60, 'bottle', 10],
        ['Beverages', 'Dasani Water 1L', 45, 80, 'bottle', 9],
        ['Beverages', 'Minute Maid Mango 1L', 120, 180, 'pack', 5],
        ['Beverages', 'Tusker Lager 500ml', 175, 260, 'bottle', 4],
        ['Beverages', 'Ketepa Pride Tea 100 bags', 190, 280, 'pack', 4],
        ['Beverages', 'Nescafe Classic 200g', 520, 720, 'jar', 3],
        ['Groceries', 'Pembe Maize Flour 2kg', 150, 205, 'pack', 10],
        ['Groceries', 'Mumias Sugar 1kg', 160, 215, 'pack', 9],
        ['Groceries', 'Basmati Rice 2kg', 340, 460, 'pack', 6],
        ['Groceries', 'Elianto Cooking Oil 1L', 290, 380, 'bottle', 8],
        ['Groceries', 'Exe Wheat Flour 2kg', 170, 225, 'pack', 7],
        ['Groceries', 'Brookside Fresh Milk 500ml', 52, 65, 'pack', 10],
        ['Groceries', 'Blue Band Margarine 500g', 175, 240, 'tub', 5],
        ['Groceries', 'Eggs Tray of 30', 420, 520, 'tray', 5],
        ['Groceries', 'Weetabix 24s', 395, 520, 'box', 3],
        ['Snacks', 'Krackles Chips 50g', 38, 60, 'pack', 7],
        ['Snacks', 'Cadbury Dairy Milk 80g', 120, 180, 'bar', 4],
        ['Snacks', 'Digestive Biscuits 400g', 130, 190, 'pack', 4],
        ['Household', 'Omo Washing Powder 1kg', 270, 360, 'pack', 6],
        ['Household', 'Jik Bleach 750ml', 150, 215, 'bottle', 4],
        ['Household', 'Toilet Paper 10 Rolls', 330, 450, 'pack', 6],
        ['Household', 'Sunlight Dish Soap 750ml', 140, 195, 'bottle', 4],
        ['Household', 'Kitchen Foil 30m', 210, 300, 'roll', 2],
        ['Personal Care', 'Colgate Toothpaste 100ml', 135, 190, 'tube', 6],
        ['Personal Care', 'Dettol Soap 175g', 95, 140, 'bar', 5],
        ['Personal Care', 'Nivea Body Lotion 400ml', 480, 680, 'bottle', 3],
        ['Personal Care', 'Always Maxi Pads 8s', 120, 175, 'pack', 4],
        ['Electronics', 'Phone Charger Type-C', 350, 650, 'piece', 2],
        ['Electronics', 'Earphones Wired', 280, 550, 'piece', 2],
        ['Electronics', 'AA Batteries 4-pack', 150, 240, 'pack', 3],
    ];

    /**
     * Supplier name, city.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const KIJANI_SUPPLIERS = [
        ['Bidco Africa Distributors', 'Thika'],
        ['Brookside Dairy Ltd', 'Ruiru'],
        ['Coca-Cola Beverages Kenya', 'Nairobi'],
        ['Unga Group Wholesalers', 'Nairobi'],
        ['Unilever Kenya', 'Nairobi'],
        ['Mombasa Road Electronics Hub', 'Syokimau'],
    ];

    /** Retail regulars: first name and surname are drawn independently. */
    private const FIRST_NAMES = ['Wanjiku', 'Otieno', 'Achieng', 'Kamau', 'Njeri', 'Mwangi', 'Atieno', 'Kiprop', 'Chebet', 'Omondi', 'Akinyi', 'Mutua', 'Wambui', 'Kariuki', 'Naliaka', 'Barasa', 'Muthoni', 'Kibet', 'Auma', 'Ndungu', 'Faith', 'Brian', 'Mercy', 'Kevin', 'Grace', 'Dennis'];

    private const LAST_NAMES = ['Mwangi', 'Otieno', 'Kamau', 'Wafula', 'Njoroge', 'Odhiambo', 'Kipchoge', 'Mutiso', 'Wekesa', 'Karanja', 'Onyango', 'Cheruiyot', 'Maina', 'Juma', 'Naibei', 'Gitau', 'Langat', 'Owino', 'Muriuki', 'Koech'];

    /** Wholesale customers on credit, with their credit limit. */
    private const KIJANI_WHOLESALE = [
        ['Mama Rosa Hotel', 150000],
        ['Sunrise Academy', 250000],
        ['Green Valley Restaurant', 180000],
        ['Kibera Community Kitchen', 90000],
        ['Highway Guest House', 200000],
        ['Eastleigh Mini Mart', 300000],
    ];

    /**
     * The businesses to build, each owned by a different person.
     *
     * @return list<array<string, mixed>>
     */
    private function profiles(): array
    {
        return [
            [
                'name' => 'Kijani Retail',
                'domain' => 'demo.test',
                'sku' => 'KJ',
                'owner' => 'Amina Hassan',
                'accountant' => 'Grace Atieno',
                'clerk' => 'Moses Kiplagat',
                'managers' => ['Peter Njuguna', 'Lucy Wairimu', 'Samuel Ochieng'],
                'cashiers' => [['Esther Moraa', 'Hassan Abdi'], ['Joyce Nyambura', 'Victor Kimani'], ['Salome Jepkosgei', 'Daniel Mutiso']],
                'branches' => self::KIJANI_BRANCHES,
                'catalogue' => self::KIJANI_CATALOGUE,
                'suppliers' => self::KIJANI_SUPPLIERS,
                'wholesale' => self::KIJANI_WHOLESALE,
                'low' => [[0, 'Dasani Water 1L', 6], [0, 'Eggs Tray of 30', 0], [1, 'Brookside Fresh Milk 500ml', 9], [1, 'Pembe Maize Flour 2kg', 4], [2, 'Elianto Cooking Oil 1L', 8], [2, 'Toilet Paper 10 Rolls', 3]],
            ],
            [
                'name' => 'Mkulima Hardware & Agrovet',
                'domain' => 'mkulima.demo.test',
                'sku' => 'MK',
                'owner' => 'John Kipchumba',
                'accountant' => 'Ruth Chepkoech',
                'clerk' => 'Evans Mbugua',
                'managers' => ['Paul Kiprotich', 'Ann Wangari', 'Joseph Kimani'],
                'cashiers' => [['Mary Jelimo', 'Isaac Rotich'], ['Beatrice Kemunto', 'Collins Bett'], ['Lilian Wanjiru', 'Stephen Ngugi']],
                'branches' => [
                    ['Nakuru', 'NKR', 70000, 'Kenyatta Avenue, Nakuru'],
                    ['Eldoret', 'ELD', 95000, 'Uganda Road, Eldoret'],
                    ['Naivasha', 'NVS', 45000, 'Moi South Lake Road, Naivasha'],
                ],
                'catalogue' => [
                    ['Building', 'Bamburi Cement 50kg', 780, 920, 'bag', 10],
                    ['Building', 'Y12 Steel Bar 12m', 1280, 1500, 'piece', 5],
                    ['Building', 'Roofing Nails 1kg', 160, 240, 'pack', 6],
                    ['Building', 'Mabati Iron Sheet Gauge 30', 640, 780, 'sheet', 6],
                    ['Building', 'Wire Nails 3 inch 1kg', 150, 220, 'pack', 7],
                    ['Paint', 'Crown Emulsion Paint 4L', 1150, 1500, 'tin', 4],
                    ['Paint', 'Paint Brush 4 inch', 90, 160, 'piece', 5],
                    ['Plumbing', 'PVC Pipe 1 inch 6m', 520, 720, 'piece', 4],
                    ['Plumbing', 'Water Tap Brass', 260, 400, 'piece', 4],
                    ['Plumbing', 'Teflon Tape', 25, 50, 'roll', 6],
                    ['Electrical', 'Twin Cable 1.5mm 100m', 4300, 5400, 'roll', 2],
                    ['Electrical', 'LED Bulb 12W', 130, 220, 'piece', 8],
                    ['Electrical', 'Extension Socket 4-way', 380, 600, 'piece', 3],
                    ['Tools', 'Claw Hammer 16oz', 320, 480, 'piece', 3],
                    ['Tools', 'Padlock 50mm', 280, 450, 'piece', 4],
                    ['Tools', 'Wheelbarrow', 3600, 4800, 'piece', 1],
                    ['Agrovet', 'Dairy Meal 70kg', 2450, 2850, 'bag', 7],
                    ['Agrovet', 'Layers Mash 70kg', 2700, 3100, 'bag', 6],
                    ['Agrovet', 'Maize Seed Hybrid 2kg', 520, 680, 'pack', 8],
                    ['Agrovet', 'DAP Fertilizer 50kg', 3900, 4500, 'bag', 5],
                    ['Agrovet', 'Dewormer 100ml', 340, 520, 'bottle', 5],
                    ['Agrovet', 'Acaricide Dip 1L', 760, 1050, 'bottle', 4],
                    ['Agrovet', 'Knapsack Sprayer 16L', 2600, 3500, 'piece', 2],
                    ['Agrovet', 'Napier Grass Cuttings Bundle', 80, 150, 'bundle', 6],
                ],
                'suppliers' => [
                    ['Bamburi Cement Dealers', 'Nakuru'],
                    ['Devki Steel Mills', 'Ruiru'],
                    ['Crown Paints Kenya', 'Nairobi'],
                    ['Unga Farm Care', 'Nairobi'],
                    ['Kenya Seed Company', 'Kitale'],
                    ['Eldoret Tools & Hardware Wholesalers', 'Eldoret'],
                ],
                'wholesale' => [
                    ['Rift Valley Contractors', 280000],
                    ['Naivasha Flower Farm', 350000],
                    ['Kapsoya Dairy Cooperative', 200000],
                    ['Lanet Builders Ltd', 180000],
                    ['Uasin Gishu Poultry Farmers', 150000],
                    ['Gilgil Horticulture Growers', 220000],
                ],
                'low' => [[0, 'Bamburi Cement 50kg', 7], [0, 'Teflon Tape', 0], [1, 'Dairy Meal 70kg', 5], [1, 'LED Bulb 12W', 9], [2, 'Maize Seed Hybrid 2kg', 6], [2, 'Dewormer 100ml', 3]],
            ],
        ];
    }

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('The demo data is only built in the local or testing environment.');
        }

        $this->ensureCommand();
        $this->start = now()->startOfDay()->subDays(self::DAYS)->setTime(8, 0);
        Determinism::start(self::SEED, $this->start->toDateTimeString());
        $tenant = app(TenantContext::class);
        $previousBusiness = $tenant->business();

        try {
            DB::transaction(function (): void {
                $this->call([PermissionSeeder::class, RoleSeeder::class]);

                foreach ($this->profiles() as $index => $profile) {
                    $this->profile = $profile;
                    $this->profileIndex = $index;
                    $this->managers = $this->cashiers = $this->sources = $this->products = $this->regulars = $this->wholesale = [];
                    Determinism::travelTo($this->start);

                    $this->business = Business::create(['name' => $profile['name'], 'currency' => 'KES', 'timezone' => 'Africa/Nairobi']);
                    app(TenantContext::class)->set($this->business);

                    $this->people();
                    $this->lists();
                    $this->catalogue();
                    $this->customers();
                    $this->purchasing();
                    $this->openingStock();
                    $this->trading();
                    $this->creditPayments();
                    $this->returnsAndRefunds();
                    $this->expenses();
                    $this->runningLow();
                }
            });
        } finally {
            $tenant->set($previousBusiness);
            Auth::forgetUser();
            Determinism::stop();
        }
    }

    // --- people, shops, lists ---------------------------------------------------

    private function people(): void
    {
        $domain = $this->profile['domain'];
        $this->owner = $this->user("owner@{$domain}", $this->profile['owner'], 'super-admin');

        $this->shops = collect($this->profile['branches'])->map(fn (array $branch): Shop => Shop::factory()->create([
            'name' => "{$branch[0]} Branch",
            'code' => $branch[1],
            'address' => $branch[3],
            'city' => 'Nairobi',
            'state' => 'Nairobi County',
            'phone' => '0'.mt_rand(720, 729).sprintf('%06d', mt_rand(0, 999999)),
            'email' => strtolower($branch[1]).'@'.$this->profile['domain'],
            'manager_id' => $this->owner->id,
            'status' => 'active',
            'country' => 'Kenya',
            'description' => null,
            'settings' => ['currency' => 'KES', 'timezone' => 'Africa/Nairobi', 'opening_time' => '08:00', 'closing_time' => '20:00'],
        ]));

        $managers = $this->profile['managers'];
        $cashiers = $this->profile['cashiers'];

        foreach ($this->shops as $i => $shop) {
            $code = strtolower($shop->code);
            $manager = $this->user("manager.{$code}@{$domain}", $managers[$i], 'manager', [$shop]);
            $this->managers[$shop->id] = $manager;
            $shop->update(['manager_id' => $manager->id]);

            $this->cashiers[$shop->id] = collect([
                $this->user("cashier1.{$code}@{$domain}", $cashiers[$i][0], 'cashier', [$shop]),
                $this->user("cashier2.{$code}@{$domain}", $cashiers[$i][1], 'cashier', [$shop]),
            ]);
        }

        $this->user("accountant@{$domain}", $this->profile['accountant'], 'accountant');
        $this->user("stock@{$domain}", $this->profile['clerk'], 'inventory-clerk', [$this->shops[0], $this->shops[2]]);
    }

    /**
     * @param  list<Shop>  $shops
     */
    private function user(string $email, string $name, string $role, array $shops = []): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'phone' => '07'.sprintf('%08d', mt_rand(0, 99999999)),
            'password' => Determinism::PASSWORD_HASH,
            'status' => 'active',
        ]);
        $user->assignRole($role);
        $user->shops()->attach(collect($shops)->pluck('id')->all());

        return $user;
    }

    private function lists(): void
    {
        foreach (['Walk-in', 'Phone Order', 'WhatsApp', 'Instagram'] as $order => $name) {
            $this->sources[$name] = SaleSource::firstOrCreate(['business_id' => $this->business->id, 'name' => $name], ['is_active' => true, 'sort_order' => $order + 1]);
        }

        foreach (['Sendy', 'Glovo Kenya', 'Wells Fargo Couriers'] as $name) {
            DeliveryCompany::factory()->create(['name' => $name]);
        }

        $types = [
            'Rent' => ExpenseCategoryType::RENT,
            'Electricity & Water' => ExpenseCategoryType::UTILITIES,
            'Salaries' => ExpenseCategoryType::PAYROLL,
            'Transport & Delivery' => ExpenseCategoryType::TRANSPORT,
            'Marketing' => ExpenseCategoryType::MARKETING,
            'Repairs & Maintenance' => ExpenseCategoryType::MAINTENANCE,
        ];
        foreach ($types as $name => $type) {
            ExpenseCategory::factory()->create(['name' => $name, 'type' => $type]);
        }

        foreach ($this->profile['suppliers'] as [$name, $city]) {
            Supplier::factory()->withOrders()->withGoodDeliveryRecord()->create([
                'name' => $name,
                'city' => $city,
                'state' => 'Kenya',
                'country' => 'Kenya',
                'currency' => 'KES',
                'email' => 'orders@'.Str::slug($name).'.test',
                'contact_email' => 'sales@'.Str::slug($name).'.test',
            ]);
        }
    }

    // --- catalogue and customers ------------------------------------------------

    /**
     * Every shop carries its own copy of the catalogue: a sale belongs to the
     * shop of its first product, so a product is owned by one shop.
     */
    private function catalogue(): void
    {
        $categories = collect(array_unique(array_column($this->profile['catalogue'], 0)))
            ->mapWithKeys(fn (string $name): array => [$name => Category::factory()->create(['name' => $name, 'slug' => Str::slug($name)])]);
        $suppliers = Supplier::orderBy('id')->pluck('id');

        foreach ($this->shops as $shopIndex => $shop) {
            $this->products[$shop->id] = collect($this->profile['catalogue'])->map(function (array $row, int $i) use ($categories, $suppliers, $shop, $shopIndex): Product {
                [$category, $name, $cost, $price, $unit] = $row;

                $product = Product::factory()->create([
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.strtolower($shop->code),
                    'description' => null,
                    'notes' => null,
                    'sku' => sprintf('%s-%s-%03d', $this->profile['sku'], $shop->code, $i + 1),
                    'barcode' => '6161'.sprintf('%d%d%07d', $this->profileIndex, $shopIndex, 100000 + $i * 37),
                    'category_id' => $categories[$category]->id,
                    'supplier_id' => $suppliers[$i % $suppliers->count()],
                    'shop_id' => $shop->id,
                    'cost_price' => $cost,
                    'selling_price' => $price,
                    'unit' => $unit,
                    'has_variations' => false,
                    'track_stock' => true,
                    'stock_quantity' => 0,
                    'reorder_level' => 20,
                    'status' => 'active',
                    'created_by' => $this->owner->id,
                ]);

                $product->shops()->attach($shop->id, ['reorder_level' => 20]);
                $product->syncShopVariationRows();

                return $product;
            });
        }
    }

    private function customers(): void
    {
        $name = fn (): string => self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)].' '.self::LAST_NAMES[mt_rand(0, count(self::LAST_NAMES) - 1)];
        $wholesaleChunks = array_chunk($this->profile['wholesale'], 2);

        foreach ($this->shops as $i => $shop) {
            $this->regulars[$shop->id] = Customer::factory()->forShop($shop)->count(14)->create([
                'customer_type' => 'retail',
                'allow_credit' => false,
                'created_by' => $this->managers[$shop->id]->id,
                'address' => null,
            ])->each(function (Customer $customer) use ($name): void {
                $customer->update([
                    'name' => $name(),
                    'phone' => '07'.sprintf('%08d', mt_rand(10000000, 99999999)),
                    'email' => null,
                ]);
            });

            $this->wholesale[$shop->id] = collect($wholesaleChunks[$i] ?? [])->map(function (array $row) use ($shop): Customer {
                [$name, $limit] = $row;
                $customer = Customer::factory()->forShop($shop)->withCredit($limit)->create([
                    'name' => $name,
                    'phone' => '07'.sprintf('%08d', mt_rand(10000000, 99999999)),
                    'email' => Str::slug($name).'@example.test',
                    'created_by' => $this->managers[$shop->id]->id,
                    'address' => null,
                ]);

                CreditAccount::factory()->create([
                    'customer_id' => $customer->id,
                    'shop_id' => $shop->id,
                    'credit_limit' => $limit,
                    'current_balance' => 0,
                    'available_credit' => $limit,
                    'status' => 'active',
                    'is_suspended' => false,
                ]);

                return $customer;
            });
        }
    }

    // --- purchasing and stock -----------------------------------------------------

    private function purchasing(): void
    {
        foreach (range(1, 6) as $i) {
            $shop = $this->shops[$i % $this->shops->count()];
            $order = PurchaseOrder::factory()->create([
                'shop_id' => $shop->id,
                'supplier_id' => Supplier::orderBy('id')->pluck('id')[$i % count($this->profile['suppliers'])],
                'created_by' => $this->managers[$shop->id]->id,
                'approved_by' => $this->owner->id,
            ]);

            foreach ($this->pickMany($this->products[$shop->id], 4) as $product) {
                $quantity = mt_rand(5, 20) * 10;
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'product_name' => $product->name,
                    'quantity_ordered' => $quantity,
                    'quantity_received' => 0,
                    'quantity_remaining' => $quantity,
                    'unit' => $product->unit,
                    'unit_cost' => (float) $product->cost_price,
                    'tax_rate' => 0,
                    'discount_percent' => 0,
                ]);
            }
        }
    }

    /**
     * Stock arrives through the ledger so every unit has a cost layer: once
     * before trading starts, and again halfway as a restock.
     */
    private function openingStock(): void
    {
        $this->receiveStock($this->start->copy()->subDay(), 1.0);
    }

    private function receiveStock(Carbon $moment, float $factor): void
    {
        Determinism::travelTo($moment);
        $ledger = app(StockLedger::class);

        foreach ($this->shops as $shop) {
            foreach ($this->profile['catalogue'] as $i => $row) {
                $product = $this->products[$shop->id][$i];
                $quantity = (int) round((60 + $row[5] * 25 + mt_rand(0, 40)) * $factor);

                $ledger->receive($shop->id, $product->defaultVariation()->firstOrFail(), $quantity, (float) $row[2], StockMovementType::INTAKE, [
                    'user_id' => $this->managers[$shop->id]->id,
                    'notes' => $factor === 1.0 ? 'Opening stock' : 'Restock',
                    'received_at' => $moment,
                ]);
            }
        }
    }

    // --- sales through the real paths ---------------------------------------------

    private function trading(): void
    {
        $paths = ['web', 'api'];
        $n = 0;

        foreach (range(0, self::DAYS - 1) as $day) {
            if ($day === 45) {
                $this->receiveStock($this->start->copy()->addDays($day)->setTime(7, 0), 1.2);
            }

            $date = $this->start->copy()->addDays($day);

            foreach ($this->shops as $shopIndex => $shop) {
                // Growth over the quarter, busier weekends and month-end, CBD biggest.
                $base = [7, 11, 5][$shopIndex] * (1 + $day / 150);
                $base *= $date->isWeekend() ? 1.35 : 1.0;
                $base *= in_array($date->day, [1, 2, 28, 29, 30, 31], true) ? 1.2 : 1.0;
                $count = max(1, (int) round($base * $this->scale) + mt_rand(-1, 1));

                $minutes = collect(range(1, $count))->map(fn (): int => $this->peakMinute())->sort()->values();

                foreach ($minutes as $minute) {
                    Determinism::travelTo($date->copy()->setTime(8, 0)->addMinutes($minute));
                    $this->tillSale($paths[$n++ % 2], $shop);
                }
            }
        }
    }

    /**
     * Minutes after 08:00, clustered around lunch and the evening commute.
     */
    private function peakMinute(): int
    {
        $peak = mt_rand(0, 9) < 6 ? [270, 600][mt_rand(0, 1)] : mt_rand(0, 700);

        return (int) max(0, min(719, $peak + mt_rand(-90, 90)));
    }

    private function tillSale(string $path, Shop $shop): void
    {
        $cashier = $this->pick($this->cashiers[$shop->id]);
        $wholesale = $this->wholesale[$shop->id]->isNotEmpty() && mt_rand(1, 100) <= 7;
        $customer = match (true) {
            $wholesale => $this->pick($this->wholesale[$shop->id]),
            mt_rand(1, 100) <= 45 => $this->pick($this->regulars[$shop->id]),
            default => null,
        };

        $method = $wholesale && mt_rand(1, 100) <= 70
            ? 'credit'
            : $this->pickWeighted(['mobile_money' => 50, 'cash' => 32, 'card' => 13, 'bank_transfer' => 5]);

        $lines = $this->saleLines($shop, $wholesale);
        $subtotal = collect($lines)->sum(fn (array $line): float => $line['price'] * $line['quantity']);
        $discount = ! $wholesale && $subtotal > 1500 && mt_rand(1, 100) <= 12 ? 100 : 0;

        $payload = [
            'shop_id' => $shop->id,
            'source_id' => $this->sources[$this->pickWeighted(['Walk-in' => 80, 'Phone Order' => 8, 'WhatsApp' => 8, 'Instagram' => 4])]->id,
            'delivery_location' => 'In store',
            'payment_method' => $method,
            'items' => $lines,
            'discount_amount' => $discount,
        ];

        if ($customer) {
            $payload['customer_id'] = $customer->id;
        } else {
            $payload['walk_in_customer_name'] = self::FIRST_NAMES[mt_rand(0, count(self::FIRST_NAMES) - 1)];
            $payload['walk_in_customer_phone'] = '07'.sprintf('%08d', mt_rand(10000000, 99999999));
        }

        $this->postSale($path, $cashier, $payload);
    }

    /**
     * Popular items sell more often; wholesale buyers take larger quantities.
     *
     * @return list<array{product_id: int, quantity: int, price: float}>
     */
    private function saleLines(Shop $shop, bool $wholesale): array
    {
        $weights = collect($this->profile['catalogue'])->map(fn (array $row): int => $row[5])->all();
        $chosen = [];
        $wanted = $wholesale ? mt_rand(3, 6) : $this->pickWeighted([1 => 30, 2 => 30, 3 => 22, 4 => 12, 5 => 6]);

        while (count($chosen) < $wanted) {
            $roll = mt_rand(1, array_sum($weights));
            foreach ($weights as $index => $weight) {
                $roll -= $weight;
                if ($roll <= 0) {
                    $chosen[$index] = true;
                    break;
                }
            }
        }

        return collect(array_keys($chosen))->map(fn (int $index): array => [
            'product_id' => $this->products[$shop->id][$index]->id,
            'quantity' => $wholesale ? mt_rand(5, 24) : $this->pickWeighted([1 => 55, 2 => 30, 3 => 10, 4 => 5]),
            'price' => (float) $this->profile['catalogue'][$index][3],
        ])->values()->all();
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

    /**
     * Wholesale customers pay what they owe, mostly; the most recent credit
     * sales, and a few older ones, stay outstanding so overdue balances show.
     */
    private function creditPayments(): void
    {
        Auth::setUser($this->owner);
        $cutoff = now()->subDays(25);

        Sale::where('payment_method', 'credit')->where('payment_status', '!=', 'paid')
            ->where('completed_at', '<', $cutoff)->orderBy('id')->get()
            ->filter(fn (Sale $sale, int $index): bool => $index % 5 !== 3)
            ->each(function (Sale $sale): void {
                Determinism::travelTo($sale->completed_at->copy()->addDays(mt_rand(5, 21)));
                $amount = mt_rand(1, 100) <= 70 ? (float) $sale->balance_due : round((float) $sale->balance_due / 2, 2);

                $request = Request::create("/sales/{$sale->uuid}/payments", 'POST', [
                    'payments' => [['amount' => $amount, 'payment_method' => $this->pickWeighted(['mobile_money' => 55, 'cash' => 25, 'bank_transfer' => 20])]],
                ], server: ['HTTP_ACCEPT' => 'application/json']);
                $request->setUserResolver(fn () => $this->owner);
                app()->instance('request', $request);

                $response = app(WebSaleController::class)->collectPayment($request, $sale);

                if ($response->getStatusCode() >= 400) {
                    throw new RuntimeException("Collecting a credit payment failed: {$response->getContent()}");
                }
            });

        Determinism::travelTo(now());
    }

    private function returnsAndRefunds(): void
    {
        Sale::with('items')->where('completed_at', '<', now()->subDays(7))->where('payment_method', '!=', 'credit')
            ->orderBy('id')->get()
            ->filter(fn (Sale $sale, int $index): bool => $index % 120 === 17)
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

    // --- expenses -----------------------------------------------------------------

    private function expenses(): void
    {
        $category = ExpenseCategory::pluck('id', 'name');
        $months = collect(range(0, 3))->map(fn (int $m): Carbon => $this->start->copy()->startOfMonth()->addMonths($m))
            ->filter(fn (Carbon $month): bool => $month->lte(now()));

        foreach ($this->shops as $i => $shop) {
            $manager = $this->managers[$shop->id];

            foreach ($months as $month) {
                $monthly = [
                    ['Rent', "Rent {$month->format('F')}", $this->profile['branches'][$i][2], 1],
                    ['Salaries', "Staff salaries {$month->format('F')}", [180000, 260000, 140000][$i], 28],
                    ['Electricity & Water', 'KPLC & water bill', mt_rand(14, 26) * 1000, 12],
                ];

                foreach ($monthly as [$name, $title, $amount, $dayOfMonth]) {
                    $date = $month->copy()->day(min($dayOfMonth, $month->daysInMonth));
                    if ($date->gt(now()) || $date->lt($this->start)) {
                        continue;
                    }
                    $this->expense($shop, $manager, $category[$name], $title, $amount, $date);
                }
            }

            foreach (range(1, 14) as $n) {
                [$name, $title, $low, $high] = [
                    ['Transport & Delivery', 'Delivery fuel', 1500, 4500],
                    ['Marketing', 'Social media promotion', 2000, 8000],
                    ['Repairs & Maintenance', 'Shelf and fridge repairs', 1200, 9000],
                    ['Transport & Delivery', 'Supplier pickup', 2500, 6000],
                ][mt_rand(0, 3)];

                $this->expense($shop, $manager, $category[$name], $title, mt_rand($low, $high), now()->subDays(mt_rand(0, self::DAYS - 1)));
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

    private function expense(Shop $shop, User $by, int $categoryId, string $title, int|float $amount, Carbon $date): void
    {
        Determinism::travelTo($date->copy()->setTime(10, 0));

        Expense::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $categoryId,
            'title' => $title,
            'amount' => $amount,
            'expense_date' => $date->toDateString(),
            'created_by' => $by->id,
        ]);
    }

    /**
     * Leaves a few lines under their reorder level and one sold out, so the
     * low-stock screens have something to show.
     */
    private function runningLow(): void
    {
        Determinism::travelTo(now());
        $ledger = app(StockLedger::class);

        foreach ($this->profile['low'] as [$shopIndex, $name, $level]) {
            $product = $this->products[$this->shops[$shopIndex]->id]->firstWhere('name', $name);
            $ledger->setLevel($this->shops[$shopIndex]->id, $product->defaultVariation()->firstOrFail(), $level, [
                'user_id' => $this->managers[$this->shops[$shopIndex]->id]->id,
                'notes' => 'Stock count',
            ]);
        }
    }

    // --- helpers -------------------------------------------------------------

    /**
     * The permission and role seeders write progress to a command; when seeded
     * from code, give them a silent one.
     */
    private function ensureCommand(): void
    {
        if (isset($this->command)) {
            return;
        }

        $command = new class extends Command
        {
            protected $name = 'demo:seed-quietly';
        };
        $command->setLaravel(app());
        $command->setOutput(new OutputStyle(new ArrayInput([]), new NullOutput));

        $this->setContainer(app())->setCommand($command);
    }

    /**
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
     * @param  array<string|int, int>  $weights  value => weight
     */
    private function pickWeighted(array $weights): string|int
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $value => $weight) {
            $roll -= $weight;
            if ($roll <= 0) {
                return $value;
            }
        }

        return array_key_first($weights);
    }
}
