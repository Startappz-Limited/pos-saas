<?php

use App\Enums\ReturnStatus;
use App\Models\CashRegister;
use App\Models\Register;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Tests\Support\Till;
use Tests\Support\TwoBusinesses;

/*
 * The shape and status of `POST /api/v1/sales/{uuid}/void` (stage 10, step
 * 5c): the v1 contract test records only sign-in and parameter-free GETs, so
 * this pins each refusal (`void_window_closed`, `sale_has_returns`,
 * `sale_already_voided`, 403 `forbidden`) and a successful void.
 */

beforeEach(function () {
    seedRolesAndPermissions();
    $this->travelTo(Carbon::parse('2026-10-26 09:00:00'));
    $this->fixture = TwoBusinesses::create();
    $this->a = $this->fixture->a;
    $this->tenant = app(TenantContext::class);
    $this->in = fn (Closure $work) => $this->tenant->run($this->a->business, $work);
    $this->register = ($this->in)(fn () => Register::defaultFor($this->a->shops[0]->id));
    $this->session = ($this->in)(fn () => CashRegister::factory()->create(['shop_id' => $this->a->shops[0]->id, 'register_id' => $this->register->id, 'user_id' => $this->a->owner->id]));
    $this->tenant->forget();
    $this->token = Till::token($this->a->owner, $this->register);
    $this->sale = fn (array $attributes = []) => ($this->in)(fn () => Sale::factory()->create([
        'shop_id' => $this->a->shops[0]->id, 'register_id' => $this->session->id, 'status' => 'completed', 'completed_at' => now(), 'paid_amount' => 0, ...$attributes,
    ]));
    $this->void = function (Sale $sale, ?string $token = null) {
        Till::freshRequest();

        return $this->withToken($token ?? $this->token)->postJson("/api/v1/sales/{$sale->uuid}/void", ['reason' => 'Rang it twice']);
    };
});

it('answers a successful void in the v1 envelope', function () {
    ($this->void)(($this->sale)())->assertOk()
        ->assertJsonStructure(['success', 'message', 'data' => ['sale_uuid', 'status', 'voided_at', 'voidable', 'items_restocked', 'money' => ['paid_out', 'refunds_due']], 'meta', 'code'])
        ->assertJsonPath('success', true)->assertJsonPath('data.status', 'voided')->assertJsonPath('code', null);
});

it('pins each refusal\'s status and code', function (Closure $setup, int $status, string $code) {
    [$sale, $token] = $setup->call($this);

    ($this->void)($sale, $token)->assertStatus($status)
        ->assertJsonStructure(['success', 'message', 'data', 'meta', 'code'])
        ->assertJsonPath('success', false)->assertJsonPath('code', $code);
})->with([
    'window closed' => [function () {
        $sale = ($this->sale)();
        ($this->in)(fn () => $this->session->update(['status' => 'closed', 'closed_at' => now()]));
        $this->travel(5)->minutes();

        return [$sale, null];
    }, 422, 'void_window_closed'],
    'has returns' => [function () {
        $sale = ($this->sale)();
        ($this->in)(fn () => SaleReturn::factory()->create(['sale_id' => $sale->id, 'shop_id' => $sale->shop_id, 'status' => ReturnStatus::PENDING]));

        return [$sale, null];
    }, 422, 'sale_has_returns'],
    'already voided' => [fn () => [($this->sale)(['status' => 'voided']), null], 422, 'sale_already_voided'],
    'no permission' => [function () {
        $manager = ($this->in)(function () {
            $user = User::factory()->memberOf($this->a->business)->create();
            $user->assignRole('manager');
            $user->shops()->attach($this->a->shops[0]->id);

            return $user;
        });
        $this->tenant->forget();

        return [($this->sale)(), Till::token($manager, $this->register, 'device-manager')];
    }, 403, 'forbidden'],
]);
