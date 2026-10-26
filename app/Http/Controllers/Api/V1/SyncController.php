<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiV1;
use App\Models\Register;
use App\Services\Sync\TradingLease;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offline sync for a paired till (Phase 7, decision 4.5). Every response
 * carries a fresh trading lease in `meta.lease`.
 */
class SyncController extends Controller
{
    public function __construct(private TenantContext $tenant, private TradingLease $leases) {}

    /**
     * A new trading lease for this till, on its own (the pull and push carry
     * one too).
     */
    public function lease(Request $request): JsonResponse
    {
        return ApiV1::ok(null, null, ['lease' => $this->freshLease($request)]);
    }

    /**
     * @return array<string, string>
     */
    private function freshLease(Request $request): array
    {
        $register = Register::query()->findOrFail($this->tenant->registerId());

        return $this->leases->present($this->leases->issue($register, $request->user()), $register);
    }
}
