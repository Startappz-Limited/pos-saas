<?php

use App\Enums\ReturnStatus;
use App\Enums\StockMovementType;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\CashPayout;
use App\Models\CashRegister;
use App\Models\RefundDue;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\StockAdjustment;
use App\Models\SyncIssue;
use App\Models\User;
use App\Notifications\Approvals\ApprovalRequested;
use App\Services\Stock\StockLedger;
use App\Services\StockAdjustmentService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/*
 * Voids from the till (stage 10, step 5c; decided 4 November 2026): online
 * through /api/v1, offline as a `sale_void` push item, judged at its recorded
 * time inside a lease; the cash comes out of the till's session open at that
 * time, whoever signed in to void; a session closed before the void synced is
 * corrected and the owners told; every till of the shop pulls the void.
 */

beforeEach(function () {
    migrateAuditDatabase();
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00')); // Monday, Nairobi
    $this->fixture = TwoBusinesses::create();
    $this->tenant = app(TenantContext::class);
    $this->a = $this->fixture->a;
    $this->shop = $this->a->shops[0];
    $this->product = $this->a->product;
    $this->in = fn (Closure $work) => $this->tenant->run($this->a->business, $work);
    [$this->register, $this->variation] = ($this->in)(fn () => [Register::defaultFor($this->shop->id), $this->product->defaultVariation]);
    ($this->in)(fn () => app(StockLedger::class)->receive($this->shop->id, $this->variation, 10, 40, StockMovementType::INTAKE));
    // The till's session, opened by the cashier: it pays a till's void whoever voids.
    $this->session = ($this->in)(fn () => CashRegister::factory()->create([
        'shop_id' => $this->shop->id, 'register_id' => $this->register->id, 'user_id' => $this->a->cashier->id,
        'opening_balance' => 1000, 'opened_at' => now()->subHour(),
    ]));
    $this->tenant->forget();
    $this->token = Till::token($this->a->owner, $this->register);
    $this->travel(1)->hours();

    $this->sale = function (array $attributes = []): Sale {
        return ($this->in)(function () use ($attributes): Sale {
            $sale = Sale::factory()->create([
                'shop_id' => $this->shop->id, 'register_id' => $this->session->id, 'status' => 'completed', 'completed_at' => now(),
                'payment_method' => 'cash', 'total_amount' => 300, 'paid_amount' => 300, 'tax_amount' => 0, 'client_uuid' => (string) Str::uuid(),
                ...$attributes,
            ]);
            $line = SaleItem::create([
                'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'product_id' => $this->product->id, 'shop_id' => $this->shop->id,
                'quantity' => 3, 'unit_price' => 100, 'line_total' => 300, 'taxable_amount' => 300, 'status' => 'completed',
            ]);
            app(StockLedger::class)->sellItem($line, $this->variation);
            $sale->register?->updateSalesTotals();

            return $sale->fresh();
        });
    };
    $this->voidOnline = function (Sale $sale, ?string $token = null) {
        Till::freshRequest();

        return $this->withToken($token ?? $this->token)->postJson("/api/v1/sales/{$sale->uuid}/void", ['reason' => 'Rang it twice']);
    };
    $this->push = function (array $data, string $recordedAt, ?string $clientUuid = null) {
        Till::freshRequest();

        return $this->withToken($this->token)->postJson('/api/v1/sync/push', ['items' => [[
            'client_uuid' => $clientUuid ?? (string) Str::uuid(), 'type' => 'sale_void', 'recorded_at' => $recordedAt,
            'data' => ['reason' => 'Rang it twice', 'voided_by' => $this->a->owner->uuid, ...$data],
        ]]]);
    };
    $this->stock = fn (): int => ($this->in)(fn () => app(StockLedger::class)->quantity($this->shop->id, $this->variation->id));
    $this->base = ($this->stock)(); // the fixture's product starts with stock of its own
    $this->setWindow = fn (string|int $value) => ($this->in)(fn () => $this->a->business->update(['settings' => array_replace_recursive($this->a->business->settings ?? [], ['sales' => ['void_window' => $value]])]));
    $this->closeSession = fn () => ($this->in)(fn () => $this->session->update(['status' => 'closed', 'closed_at' => now(), 'closing_balance' => 1300, 'expected_balance' => 1300, 'variance' => 0]));
});

describe('online', function () {
    it('voids at the till: stock back, the sale\'s cash out of its open session, the till recorded', function () {
        $sale = ($this->sale)();
        expect(($this->stock)())->toBe($this->base - 3);

        ($this->voidOnline)($sale)->assertOk()->assertJsonPath('data.status', 'voided')->assertJsonPath('data.items_restocked', 3);

        expect(($this->stock)())->toBe($this->base);
        ($this->in)(function () use ($sale): void {
            expect($sale->fresh()->voided_register_id)->toBe($this->register->id)
                ->and((float) $this->session->fresh()->calculateExpectedBalance())->toBe(1000.0);
        });
    });

    it('pays other cash out of the till\'s open session, whoever signed in to void, named on the payout', function () {
        ($this->setWindow)('end_of_day');
        $earlier = ($this->in)(fn () => CashRegister::factory()->create(['shop_id' => $this->shop->id, 'register_id' => $this->register->id, 'user_id' => $this->a->cashier->id, 'status' => 'closed', 'opened_at' => now()->subHours(3), 'closed_at' => now()->subHours(2)]));
        $sale = ($this->sale)(['register_id' => $earlier->id]);

        ($this->voidOnline)($sale)->assertOk()->assertJsonPath('data.money.paid_out.0.amount', '300.00');

        ($this->in)(function () use ($sale): void {
            $payout = CashPayout::sole();
            expect($payout->cash_register_id)->toBe($this->session->id)
                ->and($payout->reason)->toBe("Cash paid out by {$this->a->owner->name} (void of {$sale->invoice_number})")
                ->and((float) $this->session->fresh()->calculateExpectedBalance())->toBe(700.0);
        });
    });
});

describe('offline', function () {
    it('accepts a Monday void of a Monday sale pushed on Tuesday, and refuses a Tuesday void of it', function () {
        ($this->setWindow)('end_of_day');
        $sale = ($this->sale)();
        $this->travelTo(Carbon::parse('2026-10-27 08:00:00'));

        ($this->push)(['sale' => $sale->uuid], '2026-10-27 07:30:00')->assertOk()->assertJsonPath('data.results.0.status', 'refused')
            ->assertJsonPath('data.results.0.code', 'void_window_closed');
        ($this->push)(['sale' => $sale->uuid], '2026-10-26 15:00:00')->assertOk()->assertJsonPath('data.results.0.status', 'applied');

        ($this->in)(fn () => expect($sale->fresh()->status)->toBe('voided')
            ->and($sale->fresh()->void_recorded_at?->toDateTimeString())->toBe('2026-10-26 15:00:00'));
    });

    it('refuses someone without sales.void even if the till let them', function () {
        $sale = ($this->sale)();

        ($this->push)(['sale' => $sale->uuid, 'voided_by' => $this->a->cashier->uuid], now()->toDateTimeString())
            ->assertJsonPath('data.results.0.status', 'refused')->assertJsonPath('data.results.0.code', 'forbidden');
        ($this->in)(fn () => expect($sale->fresh()->status)->toBe('completed'));
    });

    it('answers pending for a void pushed before its sale, and applies it when pushed again after', function () {
        $clientUuid = (string) Str::uuid();
        $saleClientUuid = (string) Str::uuid();

        ($this->push)(['sale_client_uuid' => $saleClientUuid], now()->toDateTimeString(), $clientUuid)
            ->assertJsonPath('data.results.0.status', 'pending')->assertJsonPath('data.results.0.code', 'sale_not_synced');

        $sale = ($this->sale)(['client_uuid' => $saleClientUuid]);
        ($this->push)(['sale_client_uuid' => $saleClientUuid], now()->toDateTimeString(), $clientUuid)->assertJsonPath('data.results.0.status', 'applied');
        ($this->in)(fn () => expect($sale->fresh()->status)->toBe('voided'));
    });

    it('releases stock once when a void is pushed twice', function () {
        $sale = ($this->sale)();
        $clientUuid = (string) Str::uuid();

        ($this->push)(['sale' => $sale->uuid], now()->toDateTimeString(), $clientUuid)->assertJsonPath('data.results.0.status', 'applied');
        ($this->push)(['sale' => $sale->uuid], now()->toDateTimeString(), $clientUuid)->assertJsonPath('data.results.0.status', 'duplicate');

        expect(($this->stock)())->toBe($this->base)
            ->and(($this->in)(fn () => DB::table('stock_movements')->where('movement_type', StockMovementType::SALE_VOID->value)->count()))->toBe(1);
    });

    it('applies a void of a sale already voided elsewhere as a no-op, so the till clears it', function () {
        $sale = ($this->sale)();
        ($this->voidOnline)($sale)->assertOk();

        ($this->push)(['sale' => $sale->uuid], now()->toDateTimeString())
            ->assertJsonPath('data.results.0.status', 'applied')->assertJsonPath('data.results.0.result.already_voided', true);
    });

    it('refuses a void of a sale returned since, and puts it on the till sync review', function () {
        $sale = ($this->sale)();
        ($this->in)(fn () => SaleReturn::factory()->create(['sale_id' => $sale->id, 'shop_id' => $this->shop->id, 'status' => ReturnStatus::PENDING]));

        ($this->push)(['sale' => $sale->uuid], now()->toDateTimeString())->assertJsonPath('data.results.0.code', 'sale_has_returns');

        ($this->in)(fn () => expect(SyncIssue::query()->where('type', SyncIssue::VOID_REFUSED)->sole()->details['invoice_number'])->toBe($sale->invoice_number));
    });

    it('pays the cash out of the session open at the void\'s time, correcting it when it closed before the void synced, and telling the owners', function () {
        $sale = ($this->sale)(['status' => 'pending', 'completed_at' => null, 'paid_amount' => 0]);
        ($this->in)(fn () => SalePayment::create(['business_id' => $this->a->business->id, 'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'payment_number' => 'P-1', 'amount' => 200, 'payment_method' => 'cash', 'paid_at' => now()]));
        ($this->in)(fn () => $sale->update(['paid_amount' => 200]));
        $recordedAt = now()->toDateTimeString();
        $this->travel(1)->hours();
        ($this->closeSession)();

        ($this->push)(['sale' => $sale->uuid], $recordedAt)->assertJsonPath('data.results.0.status', 'applied');

        ($this->in)(function (): void {
            $session = $this->session->fresh();
            expect(CashPayout::sole()->cash_register_id)->toBe($session->id)
                ->and((float) $session->expected_balance)->toBe(800.0) // 1,000 opening − 200 paid out (the pending sale's cash was never in its totals)
                ->and((float) $session->variance)->toBe(500.0)
                ->and(Alert::query()->where('alertable_type', CashRegister::class)->where('alertable_id', $session->id)->exists())->toBeTrue()
                ->and($this->a->owner->notifications()->where('type', ApprovalRequested::class)->get()->contains(fn ($n) => $n->data['kind'] === 'sale_review'))->toBeTrue()
                ->and(AuditLog::query()->where('auditable_type', CashRegister::class)->where('auditable_id', $session->id)->get()->contains(fn ($row) => in_array('corrected', $row->tags ?? [], true)))->toBeTrue();
        });
    });

    it('owes the cash when the till had no session open at the void\'s time', function () {
        $sale = ($this->sale)(['status' => 'pending', 'completed_at' => null, 'paid_amount' => 0]);
        ($this->in)(fn () => SalePayment::create(['business_id' => $this->a->business->id, 'uuid' => (string) Str::uuid(), 'sale_id' => $sale->id, 'payment_number' => 'P-2', 'amount' => 200, 'payment_method' => 'cash', 'paid_at' => now()]));
        ($this->in)(fn () => $sale->update(['paid_amount' => 200]));
        ($this->closeSession)();
        $this->travel(10)->minutes();

        ($this->push)(['sale' => $sale->uuid], now()->toDateTimeString())->assertJsonPath('data.results.0.status', 'applied');

        ($this->in)(fn () => expect(CashPayout::count())->toBe(0)
            ->and(RefundDue::sole()->only(['method', 'status']))->toBe(['method' => 'cash', 'status' => 'pending']));
    });
});

describe('what the tills pull', function () {
    it('sends another till of the shop the void and the restored stock', function () {
        $sale = ($this->sale)();
        $second = ($this->in)(fn () => Register::create(['shop_id' => $this->shop->id, 'name' => 'Till 2', 'code' => 'R2']));
        $this->tenant->forget();
        $other = Till::token($this->a->owner, $second, 'device-2');
        $this->travel(2)->seconds();
        Till::freshRequest();
        $cursor = $this->withToken($other)->getJson('/api/v1/sync/pull')->assertOk()->json('meta.cursor');

        $this->travel(5)->seconds();
        ($this->voidOnline)($sale)->assertOk();
        $this->travel(2)->seconds();
        Till::freshRequest();
        $data = $this->withToken($other)->getJson('/api/v1/sync/pull?since='.urlencode($cursor))->assertOk()->json('data');

        expect(collect($data['sale_voids'])->pluck('uuid'))->toContain($sale->uuid)
            ->and(collect($data['stock'])->firstWhere('product_uuid', $this->product->uuid)['quantity'] ?? null)->toBe($this->base);
    });

    it('sends the units a pending decrease would remove, and moves the row on when one is made', function () {
        Till::freshRequest();
        $cursor = $this->withToken($this->token)->getJson('/api/v1/sync/pull')->assertOk()->json('meta.cursor');
        $this->travel(5)->seconds();
        ($this->in)(function (): void {
            $this->actingAs($this->a->cashier);
            app(StockAdjustmentService::class)->createAdjustment(['shop_id' => $this->shop->id, 'reason' => 'damaged', 'items' => [['product_id' => $this->product->id, 'quantity_change' => -2]]], $this->a->cashier->id);
        });
        $this->travel(2)->seconds();
        Till::freshRequest();
        $stock = collect($this->withToken($this->token)->getJson('/api/v1/sync/pull?since='.urlencode($cursor))->assertOk()->json('data.stock'));

        expect($stock->firstWhere('product_uuid', $this->product->uuid)['pending_removal'] ?? null)->toBe(2)
            ->and(($this->in)(fn () => StockAdjustment::sole()->status))->toBe('pending');
    });

    it('gives the same voidable to the cashier and the owner: false with a return, true with no limit for a pending sale with payments', function () {
        $sale = ($this->sale)();
        $receipt = function (User $user, Sale $sale) {
            Sanctum::actingAs($user, ['*']);
            Till::freshRequest();

            return $this->withToken(Till::token($user, $this->register, 'device-'.$user->id))->getJson("/api/v1/sales/{$sale->uuid}/receipt")->json('data.sale');
        };
        $owner = $receipt($this->a->owner, $sale);
        expect($owner['voidable'])->toBeTrue()->and($owner['voidable_until'])->not->toBeNull();

        ($this->in)(fn () => SaleReturn::factory()->create(['sale_id' => $sale->id, 'shop_id' => $this->shop->id, 'status' => ReturnStatus::PENDING]));
        expect($receipt($this->a->owner, $sale)['voidable'])->toBeFalse();

        $pending = ($this->sale)(['status' => 'pending', 'completed_at' => null, 'paid_amount' => 100]);
        expect($receipt($this->a->owner, $pending))->toMatchArray(['voidable' => true, 'voidable_until' => null]);
    });
});

it('answers 404 for another business\'s sale', function () {
    $other = $this->fixture->b->sale;

    Till::freshRequest();
    $this->withToken($this->token)->postJson("/api/v1/sales/{$other->uuid}/void", ['reason' => 'x'])->assertNotFound();
});
