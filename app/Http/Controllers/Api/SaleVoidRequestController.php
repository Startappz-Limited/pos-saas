<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Support\Sales\VoidRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Void requests on the flat API (stage 10, step 2), for the app: a cashier
 * asks, an approver lists and settles. `{success, data}`; a refusal the person
 * can act on (window closed, expired, already settled) is 422 with a message.
 */
class SaleVoidRequestController extends Controller
{
    public function __construct(private VoidRequests $requests) {}

    /**
     * Pending requests the signed-in person may approve or cancel.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SaleVoidRequest::class);

        $pending = SaleVoidRequest::query()->pending()
            ->with(['sale:id,uuid,invoice_number,total_amount,status', 'shop:id,uuid,name', 'requester:id,uuid,name'])
            ->orderBy('requested_at')
            ->get()
            ->filter(fn (SaleVoidRequest $voidRequest): bool => $request->user()->can('approve', $voidRequest) || $request->user()->can('cancel', $voidRequest))
            ->values();

        return response()->json(['success' => true, 'data' => $pending]);
    }

    public function store(Request $request, Sale $sale): JsonResponse
    {
        $this->authorize('requestVoid', $sale);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return $this->respond(fn () => $this->requests->request($sale, $request->user(), $validated['reason']), 201);
    }

    public function approve(Request $request, SaleVoidRequest $voidRequest): JsonResponse
    {
        $this->authorize('approve', $voidRequest);

        return $this->respond(fn () => $this->requests->approve($voidRequest, $request->user()));
    }

    public function reject(Request $request, SaleVoidRequest $voidRequest): JsonResponse
    {
        $this->authorize('reject', $voidRequest);
        $validated = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        return $this->respond(fn () => $this->requests->reject($voidRequest, $request->user(), $validated['rejection_reason']));
    }

    public function cancel(Request $request, SaleVoidRequest $voidRequest): JsonResponse
    {
        $this->authorize('cancel', $voidRequest);

        return $this->respond(fn () => $this->requests->cancel($voidRequest, $request->user()));
    }

    /**
     * @param  callable(): SaleVoidRequest  $action
     */
    private function respond(callable $action, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $action()->load(['sale:id,uuid,invoice_number,status'])], $status);
        } catch (BusinessRuleException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
