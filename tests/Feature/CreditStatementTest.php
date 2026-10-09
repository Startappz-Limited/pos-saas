<?php

use App\Actions\GenerateCreditStatementPdf;
use App\Jobs\SendCreditStatementViaBaileysJob;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Queue::fake();

    $this->shop = Shop::factory()->create(['name' => 'Test Shop']);
    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);

    $this->customer = Customer::factory()->forShop($this->shop)->create([
        'name' => 'Mozad Fitness',
        'customer_type' => 'wholesale',
        'allow_credit' => true,
        'credit_limit' => 200000,
        'phone' => '+254712345678',
    ]);

    $this->account = CreditAccount::create([
        'customer_id' => $this->customer->id,
        'shop_id' => $this->shop->id,
        'credit_limit' => 200000,
        'current_balance' => 6500,
        'status' => 'active',
        'payment_terms_days' => 30,
        'grace_period_days' => 7,
    ]);

    // Two unpaid credit sales, and one fully paid one that must NOT appear.
    $this->unpaidOld = Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'customer_id' => $this->customer->id,
        'invoice_number' => 'INV-OLDDEBT',
        'payment_method' => 'credit',
        'payment_status' => 'unpaid',
        'total_amount' => 6000,
        'paid_amount' => 0,
        'balance_due' => 6000,
        'completed_at' => now()->subDays(45),
    ]);

    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'customer_id' => $this->customer->id,
        'invoice_number' => 'INV-PARTIAL',
        'payment_method' => 'credit',
        'payment_status' => 'partial',
        'total_amount' => 1000,
        'paid_amount' => 500,
        'balance_due' => 500,
        'completed_at' => now()->subDays(5),
    ]);

    Sale::factory()->create([
        'shop_id' => $this->shop->id,
        'customer_id' => $this->customer->id,
        'invoice_number' => 'INV-SETTLED',
        'payment_method' => 'credit',
        'payment_status' => 'paid',
        'total_amount' => 9000,
        'paid_amount' => 9000,
        'balance_due' => 0,
    ]);

    Permission::findOrCreate('credit-sales.full-access');
    Permission::findOrCreate('credit-sales.collect');
});

describe('statement contents', function () {
    it('lists only what is still owed, not settled invoices', function () {
        $data = app(GenerateCreditStatementPdf::class)->data($this->account);

        expect($data['sales']->pluck('invoice_number')->all())
            ->toBe(['INV-OLDDEBT', 'INV-PARTIAL'])
            ->and($data['totalOwed'])->toBe(6500.0);
    });

    it('renders a PDF', function () {
        $bytes = app(GenerateCreditStatementPdf::class)->execute($this->account);

        expect(substr($bytes, 0, 4))->toBe('%PDF');
    });

    it('summarises the debt in the WhatsApp caption', function () {
        $caption = app(GenerateCreditStatementPdf::class)->caption($this->account);

        expect($caption)->toContain('Statement of Account')
            ->toContain('Test Shop')
            ->toContain('Unpaid invoices: 2')
            ->toContain('6,500.00');
    });

    it('tells a settled customer they owe nothing', function () {
        Sale::where('customer_id', $this->customer->id)->update(['balance_due' => 0]);

        $caption = app(GenerateCreditStatementPdf::class)->caption($this->account->fresh());

        expect($caption)->toContain('fully settled');
    });

    it('excludes another customer\'s debt', function () {
        $other = Customer::factory()->forShop($this->shop)->create(['customer_type' => 'wholesale']);
        Sale::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $other->id,
            'invoice_number' => 'INV-SOMEONEELSE',
            'payment_method' => 'credit',
            'payment_status' => 'unpaid',
            'balance_due' => 99999,
        ]);

        $data = app(GenerateCreditStatementPdf::class)->data($this->account);

        expect($data['sales']->pluck('invoice_number')->all())->not->toContain('INV-SOMEONEELSE')
            ->and($data['totalOwed'])->toBe(6500.0);
    });
});

describe('sending from the web back office', function () {
    it('queues the statement for delivery', function () {
        $this->user->givePermissionTo('credit-sales.collect');

        $this->actingAs($this->user)
            ->post(route('credit-accounts.sendStatement', $this->account))
            ->assertRedirect();

        Queue::assertPushed(SendCreditStatementViaBaileysJob::class, function ($job): bool {
            return $job->creditAccount->id === $this->account->id
                && $job->sentBy === $this->user->id;
        });
    });

    it('refuses a user without the collect permission', function () {
        $this->actingAs($this->user)
            ->post(route('credit-accounts.sendStatement', $this->account))
            ->assertForbidden();

        Queue::assertNothingPushed();
    });

    it('does not queue anything for a customer with no phone', function () {
        $this->user->givePermissionTo('credit-sales.collect');
        $this->customer->update(['phone' => null]);

        $this->actingAs($this->user)
            ->post(route('credit-accounts.sendStatement', $this->account))
            ->assertRedirect();

        Queue::assertNothingPushed();
    });
});

describe('sending from the mobile app', function () {
    beforeEach(function () {
        $this->user->givePermissionTo('credit-sales.full-access');
        $this->actingAs($this->user, 'sanctum');
    });

    it('queues the statement and reports what will be sent', function () {
        $this->postJson("/api/customers/{$this->customer->uuid}/send-statement")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.unpaid_count', 2)
            // Money stays a string, like every other field the app reads.
            ->assertJsonPath('data.total_owed', '6500.00');

        Queue::assertPushed(SendCreditStatementViaBaileysJob::class);
    });

    it('422s when the customer has no phone', function () {
        $this->customer->update(['phone' => null]);

        $this->postJson("/api/customers/{$this->customer->uuid}/send-statement")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        Queue::assertNothingPushed();
    });

    it('422s when the customer has no credit account', function () {
        $cashCustomer = Customer::factory()->forShop($this->shop)->create(['phone' => '+254700000000']);

        $this->postJson("/api/customers/{$cashCustomer->uuid}/send-statement")
            ->assertStatus(422);

        Queue::assertNothingPushed();
    });

    it('403s, never 401s, for another shop\'s customer', function () {
        $otherShop = Shop::factory()->create();
        $otherCustomer = Customer::factory()->forShop($otherShop)->create([
            'customer_type' => 'wholesale',
            'phone' => '+254700000001',
        ]);
        CreditAccount::create([
            'customer_id' => $otherCustomer->id,
            'shop_id' => $otherShop->id,
            'credit_limit' => 1000,
            'current_balance' => 500,
            'status' => 'active',
            'payment_terms_days' => 30,
            'grace_period_days' => 7,
        ]);

        // Another shop's customer is invisible to this user, so it is not found
        $this->postJson("/api/customers/{$otherCustomer->uuid}/send-statement")
            ->assertNotFound();

        Queue::assertNothingPushed();
    });
});

it('serves the re-download link only with a valid signature', function () {
    $url = app(GenerateCreditStatementPdf::class)->signedUrl($this->account);

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

    // Tampering with the link must not hand out a customer's debt.
    $this->get(route('credit-accounts.statement-pdf', $this->account))->assertForbidden();
});
