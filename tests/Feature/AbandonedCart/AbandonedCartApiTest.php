<?php

use App\Enums\AbandonedCartStatus;
use App\Enums\AlertType;
use App\Jobs\AbandonedCartWriteBackJob;
use App\Models\AbandonedCart;
use App\Models\AbandonedCartItem;
use App\Models\Alert;
use App\Models\BaileysMessage;
use App\Models\BaileysSession;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleSource;
use App\Models\Shop;
use App\Models\User;
use Database\Factories\BusinessFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

require_once __DIR__.'/helpers.php';

/**
 * The staff-facing API: list/show/update, notes & reminders, WhatsApp, convert
 * and opt-out — with shop isolation and the recovery link kept out of lists.
 */
beforeEach(function () {
    foreach (['view', 'update', 'contact', 'convert', 'full-access'] as $ability) {
        Permission::findOrCreate("abandoned-carts.{$ability}");
    }

    config([
        'tax.enabled' => true,
        'tax.rates.standard' => 16.0,
        'tax.default_class' => 'standard',
        'tax.prices_include_tax' => true,
        'tax.fees_taxable' => false,
    ]);

    $this->shop = acrShop(['code' => 'NRB']);
    $this->otherShop = acrShop(['code' => 'MSA']);

    $this->user = User::factory()->create();
    $this->user->shops()->attach($this->shop);
    $this->user->givePermissionTo(['abandoned-carts.view', 'abandoned-carts.update', 'abandoned-carts.contact', 'abandoned-carts.convert']);

    $this->cart = AbandonedCart::factory()->for($this->shop)->create([
        'platform_cart_id' => '501',
        'customer_name' => 'Jane Wanjiku',
        'customer_phone' => '0712345678',
        'checkout_link' => 'https://shop.test/?acr_recover=501&token=SECRET-TOKEN',
        'total' => 1160,
    ]);

    $this->foreignCart = AbandonedCart::factory()->for($this->otherShop)->create([
        'platform_cart_id' => '777',
        'customer_name' => 'Other Shop Customer',
    ]);
});

function sellableCart(AbandonedCart $cart, int $quantity = 1, float $price = 1160): Product
{
    $product = Product::factory()->create([
        'shop_id' => $cart->shop_id,
        'selling_price' => 1160,
        'cost_price' => 600,
        'stock_quantity' => 10,
        'status' => 'active',
    ]);

    AbandonedCartItem::factory()->create([
        'abandoned_cart_id' => $cart->id,
        'product_id' => $product->id,
        'name' => 'Yoga Mat',
        'quantity' => $quantity,
        'unit_price' => $price,
        'line_total' => $quantity * $price,
    ]);

    return $product;
}

describe('access', function () {
    it('requires authentication', function () {
        $this->getJson('/api/abandoned-carts')->assertUnauthorized();
    });

    it('requires the abandoned-carts.view permission', function () {
        Sanctum::actingAs(staffUser());

        $this->getJson('/api/abandoned-carts')->assertForbidden();
    });

    it('lists only carts of the user\'s shops', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/abandoned-carts')->assertOk();

        expect($response->json('success'))->toBeTrue()
            ->and(collect($response->json('data.carts.data'))->pluck('uuid')->all())->toBe([$this->cart->uuid])
            ->and($response->json('data.statistics.total'))->toBe(1);
    });

    it('refuses a shop filter for a shop the user cannot access', function () {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/abandoned-carts?shop_id='.$this->otherShop->id)->assertForbidden();
    });

    it('refuses every action on another shop\'s cart', function (string $method, string $suffix) {
        Sanctum::actingAs($this->user);

        // Another shop's cart is invisible to this user, so it is not found
        $this->json($method, "/api/abandoned-carts/{$this->foreignCart->uuid}{$suffix}", [
            'message' => 'x', 'title' => 'x', 'scheduled_at' => now()->addDay()->toIso8601String(), 'payment_method' => 'cash', 'status' => 'lost',
        ])->assertNotFound();
    })->with([
        'show' => ['GET', ''],
        'update' => ['PATCH', ''],
        'note' => ['POST', '/notes'],
        'reminder' => ['POST', '/reminders'],
        'whatsapp' => ['POST', '/whatsapp'],
        'convert' => ['POST', '/convert'],
        'opt-out' => ['POST', '/opt-out'],
    ]);

    it('lets a view-only user read but not act', function () {
        $viewer = User::factory()->create();
        $viewer->shops()->attach($this->shop);
        $viewer->givePermissionTo('abandoned-carts.view');
        Sanctum::actingAs($viewer);

        $this->getJson("/api/abandoned-carts/{$this->cart->uuid}")->assertOk();
        $this->patchJson("/api/abandoned-carts/{$this->cart->uuid}", ['status' => 'lost'])->assertForbidden();
        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/convert", ['payment_method' => 'cash'])->assertForbidden();
    });
});

describe('the recovery link', function () {
    it('never appears in the list', function () {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/abandoned-carts')->assertOk();

        expect($response->getContent())->not->toContain('SECRET-TOKEN')
            ->and($response->json('data.carts.data.0'))->not->toHaveKey('checkout_link')
            ->and($response->json('data.carts.data.0'))->not->toHaveKey('recovery_link')
            ->and($response->json('data.carts.data.0.has_recovery_link'))->toBeTrue();
    });

    it('is on the single cart for staff who can act on it', function () {
        Sanctum::actingAs($this->user);

        $this->getJson("/api/abandoned-carts/{$this->cart->uuid}")
            ->assertOk()
            ->assertJsonPath('data.recovery_link', 'https://shop.test/?acr_recover=501&token=SECRET-TOKEN')
            ->assertJsonMissingPath('data.checkout_link');
    });

    it('is withheld from view-only staff', function () {
        $viewer = User::factory()->create();
        $viewer->shops()->attach($this->shop);
        $viewer->givePermissionTo('abandoned-carts.view');
        Sanctum::actingAs($viewer);

        $response = $this->getJson("/api/abandoned-carts/{$this->cart->uuid}")->assertOk();

        expect($response->json('data.recovery_link'))->toBeNull()
            ->and($response->getContent())->not->toContain('SECRET-TOKEN');
    });
});

describe('listing', function () {
    it('filters by status, date range and search, with money as strings', function () {
        AbandonedCart::factory()->for($this->shop)->create([
            'customer_name' => 'Brian Otieno',
            'status' => AbandonedCartStatus::Lost,
            'abandoned_at' => now()->subDays(10),
        ]);
        Sanctum::actingAs($this->user);

        $byStatus = $this->getJson('/api/abandoned-carts?status=lost')->json('data.carts.data');
        $bySearch = $this->getJson('/api/abandoned-carts?search=Jane')->json('data.carts.data');
        $byDate = $this->getJson('/api/abandoned-carts?from='.now()->subDays(11)->toDateString().'&to='.now()->subDays(9)->toDateString())->json('data.carts.data');

        expect(collect($byStatus)->pluck('customer_name')->all())->toBe(['Brian Otieno'])
            ->and(collect($bySearch)->pluck('customer_name')->all())->toBe(['Jane Wanjiku'])
            ->and(collect($byDate)->pluck('customer_name')->all())->toBe(['Brian Otieno'])
            ->and($bySearch[0]['total'])->toBe('1160.00');
    });

    it('rejects an unknown status filter', function () {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/abandoned-carts?status=bogus')->assertUnprocessable();
    });
});

describe('follow-up', function () {
    it('updates status and assignee and records both on the timeline', function () {
        $colleague = User::factory()->create();
        $colleague->shops()->attach($this->shop);
        Sanctum::actingAs($this->user);

        $this->patchJson("/api/abandoned-carts/{$this->cart->uuid}", [
            'status' => 'contacted',
            'assigned_to' => $colleague->id,
            'note' => 'Called, will come in on Friday',
        ])->assertOk()->assertJsonPath('data.status', 'contacted');

        $cart = $this->cart->fresh();

        expect($cart->status)->toBe(AbandonedCartStatus::Contacted)
            ->and($cart->assigned_to)->toBe($colleague->id)
            ->and($cart->last_contacted_at)->not->toBeNull()
            ->and($cart->cartNotes()->sole()->message)->toBe('Called, will come in on Friday')
            ->and($cart->alerts()->where('data->event', 'status_changed')->count())->toBe(1);
    });

    it('will not set converted or recovered by hand', function (string $status) {
        Sanctum::actingAs($this->user);

        $this->patchJson("/api/abandoned-carts/{$this->cart->uuid}", ['status' => $status])->assertUnprocessable();
    })->with(['converted', 'recovered']);

    it('refuses to assign a user from another shop', function () {
        $outsider = User::factory()->create();
        $outsider->shops()->attach($this->otherShop);
        Sanctum::actingAs($this->user);

        $this->patchJson("/api/abandoned-carts/{$this->cart->uuid}", ['assigned_to' => $outsider->id])->assertUnprocessable();
    });

    it('adds notes and reminders, and dismisses only its own reminders', function () {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/notes", ['message' => 'Prefers WhatsApp'])
            ->assertCreated()->assertJsonPath('data.type', AlertType::CART_NOTE->value);

        $reminder = $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/reminders", [
            'title' => 'Call back',
            'scheduled_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()->json('data');

        $foreignReminder = $this->foreignCart->logActivity('Not yours', '', type: AlertType::CART_REMINDER);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/reminders/{$foreignReminder->uuid}/resolve")->assertNotFound();
        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/reminders/{$reminder['uuid']}/resolve")->assertOk();

        expect(Alert::where('uuid', $reminder['uuid'])->value('is_resolved'))->toBeTruthy();
    });

    it('records an opt-out and tells the website', function () {
        Queue::fake([AbandonedCartWriteBackJob::class]);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/opt-out", ['reason' => 'Asked us to stop'])
            ->assertOk()
            ->assertJsonPath('data.is_opted_out', true);

        Queue::assertPushed(AbandonedCartWriteBackJob::class, fn ($job) => $job->status === 'unsubscribed' && $job->cart->is($this->cart));
    });
});

describe('WhatsApp', function () {
    beforeEach(function () {
        // Messages go out through the Chatway gateway (startappz/wa-gateway-laravel).
        config()->set('wa-gateway.url', 'https://gateway.test');
        config()->set('wa-gateway.token', 'test-token');
    });

    it('sends the customer their items and recovery link over Baileys and marks the cart contacted', function () {
        Http::fake(['gateway.test/sessions/*/messages/text' => Http::response(['wa_message_id' => 'WAID-1'])]);
        BaileysSession::factory()->for($this->shop)->connected()->create();
        sellableCart($this->cart, 2, 580);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/whatsapp")->assertOk();

        $message = BaileysMessage::sole();
        $cart = $this->cart->fresh();

        expect($message->content)->toContain('Hi Jane')
            ->and($message->content)->toContain('Yoga Mat x2')
            ->and($message->content)->toContain('https://shop.test/?acr_recover=501&token=SECRET-TOKEN')
            ->and($message->chat_jid)->toBe('254712345678@s.whatsapp.net')
            ->and($cart->status)->toBe(AbandonedCartStatus::Contacted)
            ->and($cart->last_contacted_at)->not->toBeNull()
            ->and(json_encode($cart->timeline()->get()->toArray()))->not->toContain('SECRET-TOKEN');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/messages/text'));
    });

    it('refuses when the cart has no phone number', function () {
        Http::fake();
        $this->cart->update(['customer_phone' => null]);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/whatsapp")
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        Http::assertNothingSent();
    });

    it('refuses when the customer opted out', function () {
        Http::fake();
        $this->cart->update(['opted_out_at' => now()]);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/whatsapp")->assertUnprocessable();

        Http::assertNothingSent();
    });

    it('refuses when no WhatsApp session is connected', function () {
        Http::fake();
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/whatsapp")->assertUnprocessable();

        expect($this->cart->fresh()->status)->toBe(AbandonedCartStatus::Abandoned);
    });
});

describe('converting to a sale', function () {
    it('creates a VAT-correct sale with a gapless invoice number and closes the cart', function () {
        Queue::fake([AbandonedCartWriteBackJob::class]);
        $this->shop->update(['vat_registered' => true]);
        $product = sellableCart($this->cart);
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $this->cart->update(['customer_id' => $customer->id]);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/convert", ['payment_method' => 'mobile_money'])
            ->assertCreated();

        $sale = Sale::sole();
        $cart = $this->cart->fresh();
        $invoice = "INV-NRB-{$this->shop->invoice_code}-".now()->format('Y').'-000001';

        expect((float) $sale->total_amount)->toBe(1160.0)
            ->and((float) $sale->tax_amount)->toBe(160.0)
            ->and((float) $sale->taxable_amount)->toBe(1000.0)
            ->and((float) $sale->total_profit)->toBe(400.0)
            ->and($sale->tax_inclusive)->toBeTrue()
            ->and($sale->invoice_number)->toBe($invoice)
            ->and($sale->source->name)->toBe('Abandoned Cart')
            ->and($sale->customer_id)->toBe($customer->id)
            ->and($sale->payment_status)->toBe('paid')
            ->and($sale->status)->toBe('completed')
            ->and($sale->items->sole()->tax_amount)->toEqual(160.0)
            ->and($product->fresh()->stock_quantity)->toBe(9)
            ->and($cart->status)->toBe(AbandonedCartStatus::Converted)
            ->and($cart->sale_id)->toBe($sale->id)
            ->and($cart->converted_by)->toBe($this->user->id);

        Queue::assertPushed(AbandonedCartWriteBackJob::class, fn ($job) => $job->status === 'recovered'
            && $job->reference === $invoice);
    });

    it('uses the existing Abandoned Cart sale source rather than creating another', function () {
        Queue::fake([AbandonedCartWriteBackJob::class]);
        // Every business starts with an "Abandoned Cart" source (SaleSource::defaults()).
        $source = SaleSource::withoutGlobalScopes()->where(['business_id' => BusinessFactory::defaultId(), 'name' => 'Abandoned Cart'])->firstOrFail();
        sellableCart($this->cart);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/convert", ['payment_method' => 'cash'])->assertCreated();

        expect(Sale::sole()->source_id)->toBe($source->id)
            ->and(SaleSource::where('name', 'Abandoned Cart')->count())->toBe(1);
    });

    it('tells the website the cart was recovered, after the sale is saved', function () {
        Http::fake(['shop.test/wp-json/wc-acr/v1/carts/501/status' => Http::response(['id' => 501, 'status' => 'recovered', 'unsubscribed' => false])]);
        sellableCart($this->cart);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/convert", ['payment_method' => 'cash'])->assertCreated();

        Http::assertSent(fn (Request $request) => $request['status'] === 'recovered'
            && $request['reference'] === Sale::sole()->invoice_number);
        expect($this->cart->fresh()->platform_status)->toBe('recovered');
    });

    it('still converts when the website cannot be told', function () {
        Http::fake(['shop.test/*' => Http::response(['code' => 'acr_rest_forbidden'], 401)]);
        sellableCart($this->cart);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/convert", ['payment_method' => 'cash'])->assertCreated();

        expect($this->cart->fresh()->status)->toBe(AbandonedCartStatus::Converted)
            ->and($this->cart->alerts()->where('data->event', 'write_back_failed')->count())->toBe(1);
    });

    it('refuses clearly when an item is not linked to a POS product', function () {
        sellableCart($this->cart);
        AbandonedCartItem::factory()->create(['abandoned_cart_id' => $this->cart->id, 'name' => 'Mystery Gadget', 'product_id' => null]);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/convert", ['payment_method' => 'cash'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'These items are not linked to a POS product (or variation): Mystery Gadget. Link the website product to a POS product or give them matching SKUs, then try again.']);

        expect(Sale::count())->toBe(0)
            ->and($this->cart->fresh()->status)->toBe(AbandonedCartStatus::Abandoned);
    });

    it('refuses a cart already recovered online, and a second conversion', function () {
        Queue::fake([AbandonedCartWriteBackJob::class]);
        sellableCart($this->cart);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/convert", ['payment_method' => 'cash'])->assertCreated();
        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/convert", ['payment_method' => 'cash'])->assertUnprocessable();

        $recovered = AbandonedCart::factory()->for($this->shop)->create(['status' => AbandonedCartStatus::Recovered, 'platform_order_id' => '9001']);
        sellableCart($recovered);

        $this->postJson("/api/abandoned-carts/{$recovered->uuid}/convert", ['payment_method' => 'cash'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This cart was already recovered on the website as order #9001. Convert the website order instead.');

        expect(Sale::count())->toBe(1);
    });

    it('refuses credit and a customer from another shop', function () {
        sellableCart($this->cart);
        $foreignCustomer = Customer::factory()->create(['shop_id' => $this->otherShop->id]);
        Sanctum::actingAs($this->user);

        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/convert", ['payment_method' => 'credit'])->assertUnprocessable();
        $this->postJson("/api/abandoned-carts/{$this->cart->uuid}/convert", ['payment_method' => 'cash', 'customer_id' => $foreignCustomer->id])->assertUnprocessable();

        expect(Sale::count())->toBe(0);
    });
});

describe('web screens', function () {
    it('lists carts and shows the recovery link only on the cart page', function () {
        sellableCart($this->cart);
        $this->actingAs($this->user);

        $this->get(route('abandoned-carts.index'))
            ->assertOk()
            ->assertSee('Jane Wanjiku')
            ->assertDontSee('Other Shop Customer')
            ->assertDontSee('SECRET-TOKEN');

        $this->get(route('abandoned-carts.show', $this->cart))
            ->assertOk()
            ->assertSee('https://shop.test/?acr_recover=501&amp;token=SECRET-TOKEN', false)
            ->assertSee('copy-recovery-link', false);
    });

    it('hides the recovery link from view-only staff and forbids other shops', function () {
        $viewer = User::factory()->create();
        $viewer->shops()->attach($this->shop);
        $viewer->givePermissionTo('abandoned-carts.view');
        $this->actingAs($viewer);

        $this->get(route('abandoned-carts.show', $this->cart))->assertOk()->assertDontSee('SECRET-TOKEN');
        $this->get(route('abandoned-carts.show', $this->foreignCart))->assertNotFound();
    });

    it('converts from the cart page and lands on the sale', function () {
        Queue::fake([AbandonedCartWriteBackJob::class]);
        sellableCart($this->cart);
        $this->actingAs($this->user);

        $response = $this->post(route('abandoned-carts.convert', $this->cart), ['payment_method' => 'cash']);

        $response->assertRedirect(route('sales.show', Sale::sole()));
    });

    it('shows the refusal message when conversion is not possible', function () {
        AbandonedCartItem::factory()->create(['abandoned_cart_id' => $this->cart->id, 'name' => 'Mystery Gadget']);
        $this->actingAs($this->user);

        $this->from(route('abandoned-carts.show', $this->cart))
            ->post(route('abandoned-carts.convert', $this->cart), ['payment_method' => 'cash'])
            ->assertRedirect(route('abandoned-carts.show', $this->cart))
            ->assertSessionHas('error');
    });

    it('shows the sidebar link only with the permission', function () {
        Permission::findOrCreate('sales.view');
        Permission::findOrCreate('ecommerce-orders.view');
        $this->user->givePermissionTo(['sales.view', 'ecommerce-orders.view']);

        $this->actingAs($this->user)->get(route('ecommerce-orders.index'))
            ->assertOk()
            ->assertSee(route('abandoned-carts.index'), false);

        $other = staffUser($this->shop);
        $other->givePermissionTo(['sales.view', 'ecommerce-orders.view']);

        $this->actingAs($other)->get(route('ecommerce-orders.index'))
            ->assertOk()
            ->assertDontSee(route('abandoned-carts.index'), false);
    });
});
