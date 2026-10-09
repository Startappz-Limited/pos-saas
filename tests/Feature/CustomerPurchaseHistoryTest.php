<?php

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function actingAsCustomerViewer(): User
{
    /** @var User $user */
    $user = User::factory()->owner()->create();
    collect(['customers.view', 'customers.update', 'customers.delete', 'customers.activate', 'customers.deactivate'])
        ->each(fn (string $permission): Permission => Permission::findOrCreate($permission));
    $user->givePermissionTo('customers.view');
    actingAs($user);

    return $user;
}

test('customer details show purchase history and statuses', function () {
    actingAsCustomerViewer();

    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);

    $completedSale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-CUSTOMER-001',
        'total_amount' => 1500,
        'status' => 'completed',
        'payment_status' => 'paid',
        'created_at' => now()->subDay(),
    ]);
    $pendingSale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-CUSTOMER-002',
        'total_amount' => 750,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'paid_amount' => 0,
        'balance_due' => 750,
        'completed_at' => null,
    ]);
    Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => null,
        'invoice_number' => 'INV-WALK-IN-001',
    ]);

    $response = get(route('customers.show', $customer))->assertOk();

    expect($response->viewData('customer'))
        ->sales_count->toBe(2)
        ->and((float) $response->viewData('customer')->total_spent)->toBe(1500.0);

    expect($response->viewData('purchases')->pluck('id')->all())
        ->toContain($completedSale->id, $pendingSale->id);

    $response
        ->assertSee('Purchase History')
        ->assertSee('View All')
        ->assertSee('INV-CUSTOMER-001')
        ->assertSee('INV-CUSTOMER-002')
        ->assertSee('Completed')
        ->assertSee('Pending')
        ->assertSee('Paid')
        ->assertSee('Unpaid')
        ->assertDontSee('INV-WALK-IN-001');
});

test('customer purchase history page can be filtered and exported as pdf', function () {
    actingAsCustomerViewer();

    $shop = Shop::factory()->create();
    $customer = Customer::factory()->create(['shop_id' => $shop->id]);
    $otherCustomer = Customer::factory()->create(['shop_id' => $shop->id]);

    Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-CUSTOMER-PAID',
        'total_amount' => 1200,
        'paid_amount' => 1200,
        'balance_due' => 0,
        'status' => 'completed',
        'payment_status' => 'paid',
        'created_at' => now()->subDays(8),
    ]);
    $filteredSale = Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-CUSTOMER-FILTERED',
        'total_amount' => 900,
        'paid_amount' => 0,
        'balance_due' => 900,
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'completed_at' => null,
        'created_at' => now()->subDay(),
    ]);
    Sale::factory()->create([
        'shop_id' => $shop->id,
        'customer_id' => $otherCustomer->id,
        'invoice_number' => 'INV-OTHER-FILTERED',
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'created_at' => now()->subDay(),
    ]);

    $filters = [
        'date_from' => now()->subDays(2)->toDateString(),
        'date_to' => today()->toDateString(),
        'status' => 'pending',
        'payment_status' => 'unpaid',
        'search' => 'FILTERED',
    ];

    $response = get(route('customers.purchases', ['customer' => $customer, ...$filters]))->assertOk();

    expect($response->viewData('purchases')->pluck('id')->all())
        ->toBe([$filteredSale->id])
        ->and($response->viewData('summary'))
        ->count->toBe(1)
        ->total_amount->toBe(900.0)
        ->balance_due->toBe(900.0);

    $response
        ->assertSee('Purchase History: '.$customer->name)
        ->assertSee('INV-CUSTOMER-FILTERED')
        ->assertSee('Download PDF')
        ->assertDontSee('INV-CUSTOMER-PAID')
        ->assertDontSee('INV-OTHER-FILTERED');

    get(route('customers.purchases.pdf', ['customer' => $customer, ...$filters]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
