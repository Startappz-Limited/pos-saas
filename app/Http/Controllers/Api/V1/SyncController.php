<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiV1;
use App\Models\Register;
use App\Services\Sync\SyncCursor;
use App\Services\Sync\SyncPull;
use App\Services\Sync\SyncPush;
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
     * What changed in the business since `since` (the cursor of the last
     * pull; none for everything). Repeat with `meta.cursor` while
     * `meta.has_more` is true. The till drops `deletions` first, then applies
     * the rest; `staff` and `settings` are null when unchanged.
     */
    public function pull(Request $request, SyncPull $sync): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['nullable', 'string', 'max:4096'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $register = Register::query()->findOrFail($this->tenant->registerId());
        $result = $sync->pull($register, SyncCursor::decode($validated['since'] ?? null), (int) ($validated['limit'] ?? 500));

        return ApiV1::ok($result['data'], null, [
            'cursor' => $result['cursor']->encode(),
            'has_more' => $result['has_more'],
            'server_time' => now()->toIso8601String(),
            'lease' => $this->freshLease($request),
        ]);
    }

    /**
     * The till's outbox: up to `sync.push_max_items` items, applied in order,
     * one result each (`applied`, `duplicate`, `refused` or `failed`). Item
     * types: customer, sale, sale_payment, cash_session, till_sign_in. Send
     * a new customer before the sales that name it, and a sale before its
     * payments.
     */
    public function push(Request $request, SyncPush $sync): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:'.(int) config('sync.push_max_items')],
            'items.*.client_uuid' => ['required', 'uuid', 'distinct'],
            'items.*.type' => ['required', 'string', 'max:40'],
            'items.*.recorded_at' => ['required', 'date'],
            'items.*.data' => ['nullable', 'array'],
        ]);

        $register = Register::query()->findOrFail($this->tenant->registerId());
        $results = $sync->push($register, $request->user(), $validated['items']);

        return ApiV1::ok(['results' => $results], null, [
            'applied' => count(array_filter($results, fn (array $result): bool => $result['status'] === 'applied')),
            'server_time' => now()->toIso8601String(),
            'lease' => $this->freshLease($request),
        ]);
    }

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
