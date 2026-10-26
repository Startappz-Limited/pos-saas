<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Register\PairRegister;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PairRegisterRequest;
use App\Http\Responses\ApiV1;
use App\Models\Register;
use App\Models\Shop;
use App\Services\Sync\TradingLease;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registers (tills) for the Flutter POS, Phase 7: list the tills of the shops
 * the user may access, and pair this device to one.
 */
class RegisterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Register::class);

        $registers = Register::query()
            ->with('shop:id,uuid,name,code')
            ->orderBy('shop_id')->orderBy('id')
            ->get()
            ->map(fn (Register $register): array => self::present($register));

        return ApiV1::ok($registers);
    }

    /**
     * Pairs the calling device to $register and returns a token bound to it,
     * with the last invoice number the server has for its series.
     */
    public function pair(PairRegisterRequest $request, Register $register, PairRegister $pair, TenantContext $tenant, TradingLease $leases): JsonResponse
    {
        $validated = $request->validated();

        $result = $pair->execute($register, $request->user(), $validated['device_id'], $validated['device_name'] ?? null, (int) $tenant->id());
        $register = $result['register']->load('shop:id,uuid,name,code');
        $lease = $leases->present($leases->issue($register, $request->user()), $register);

        return ApiV1::ok([
            'token' => $result['token']->plainTextToken,
            'register' => self::present($register),
            'last_invoice_number' => (int) $register->last_invoice_number,
            'repaired' => $result['repaired'],
            'previous_code' => $result['previous_code'],
        ], $result['repaired']
            ? __('Till paired. It now numbers sales as :code; the device it replaced has been signed out.', ['code' => $register->code])
            : __('Till paired.'), ['lease' => $lease]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(Register $register): array
    {
        /** @var Shop|null $shop */
        $shop = $register->shop;

        return [
            'uuid' => $register->uuid,
            'name' => $register->name,
            'code' => $register->code,
            'is_default' => (bool) $register->is_default,
            'paired' => $register->device_id !== null,
            'paired_at' => $register->paired_at?->toIso8601String(),
            'last_synced_at' => $register->last_synced_at?->toIso8601String(),
            'shop' => $shop ? ['uuid' => $shop->uuid, 'name' => $shop->name, 'code' => $shop->code] : null,
        ];
    }
}
