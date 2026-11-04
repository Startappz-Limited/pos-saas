<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Sale\VoidSale;
use App\Enums\ApiErrorCode;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiV1;
use App\Models\Register;
use App\Models\Sale;
use App\Support\Sales\VoidWindow;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * A void made online at a till (stage 10, step 5c): through SalePolicy::void
 * at the current time, and the same void as the web (stock released, money
 * handed back), the cash paid out of this till's open session whoever signed
 * in to void (decided 4 November 2026). A till's token reaches its own shop's
 * sales only. Refusals: 403 `forbidden` without `sales.void`; 422
 * `void_window_closed`, `sale_has_returns` or `sale_already_voided`.
 */
class SaleVoidController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    public function store(Request $request, Sale $sale, VoidSale $void, VoidWindow $window): JsonResponse
    {
        if ($sale->status === 'voided') {
            return ApiV1::error(ApiErrorCode::SaleAlreadyVoided, __('This sale is already voided.'), 422);
        }

        $decision = Gate::inspect('void', $sale);
        if ($decision->denied()) {
            return $decision->status() === 422
                ? ApiV1::error(ApiErrorCode::tryFrom((string) $decision->code()) ?? ApiErrorCode::ValidationFailed, (string) $decision->message(), 422)
                : ApiV1::error(ApiErrorCode::Forbidden, __('You can\'t void sales.'), 403);
        }

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $register = Register::query()->findOrFail($this->tenant->registerId());

        $result = $void->execute($sale, $validated['reason'], $request->user(), till: $register);

        return ApiV1::ok([
            'sale_uuid' => $sale->uuid,
            'status' => 'voided',
            'voided_at' => $sale->fresh()->voided_at?->toIso8601String(),
            'voidable' => false,
            'items_restocked' => $result['units'],
            'money' => VoidSale::money($result),
        ], VoidSale::summary($result));
    }
}
