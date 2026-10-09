<?php

namespace App\Http\Controllers\Api;

use App\Actions\GenerateCreditStatementPdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Jobs\SendCreditStatementViaBaileysJob;
use App\Models\CreditAccount;
use App\Models\Customer;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;

        $query = Customer::query()->where('shop_id', $shopId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        $customers = $query->latest()->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $customers,
        ]);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $validated['code'] = $this->generateCustomerCode();
        $validated['uuid'] = (string) Str::uuid();
        $validated['shop_id'] = auth()->user()->shop_id ?? Shop::first()?->id;
        $validated['created_by'] = auth()->id();
        $validated['credit_balance'] = 0;

        if (! ($validated['allow_credit'] ?? false)) {
            $validated['credit_limit'] = 0;
        }

        $customer = Customer::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer created successfully.',
            'data' => $customer,
        ], 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $customer,
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $validated = $request->validated();
        $validated['updated_by'] = auth()->id();

        if (! ($validated['allow_credit'] ?? $customer->allow_credit)) {
            $validated['credit_limit'] = 0;
        }

        $customer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully.',
            'data' => $customer->fresh(),
        ]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully.',
        ]);
    }

    public function activate(Customer $customer): JsonResponse
    {
        $customer->update([
            'status' => 'active',
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer activated successfully.',
            'data' => $customer->fresh(),
        ]);
    }

    public function deactivate(Customer $customer): JsonResponse
    {
        $customer->update([
            'status' => 'inactive',
            'updated_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer deactivated successfully.',
            'data' => $customer->fresh(),
        ]);
    }

    /**
     * WhatsApp this customer their statement of outstanding invoices.
     *
     * Sits on the customer resource rather than a credit one because that is
     * where the mobile client already is — the customer detail screen — and the
     * app deliberately has no credit endpoints of its own.
     */
    public function sendStatement(Request $request, Customer $customer): JsonResponse
    {
        $account = $this->resolveCreditAccount($request, $customer);

        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'This customer has no credit account to produce a statement from.',
            ], 422);
        }

        // 403, never 401 — a 401 logs the cashier out mid-shift.
        abort_unless($request->user()->can('sendStatement', $account), 403);
        abort_unless($request->user()->canAccessShop($account->shop_id), 403);

        if (empty($customer->phone)) {
            return response()->json([
                'success' => false,
                'message' => 'This customer has no phone number on file.',
            ], 422);
        }

        SendCreditStatementViaBaileysJob::dispatch($account, $request->user()->id);

        $data = app(GenerateCreditStatementPdf::class)->data($account);

        return response()->json([
            'success' => true,
            'message' => 'Statement queued for delivery on WhatsApp.',
            'data' => [
                'customer_uuid' => $customer->uuid,
                'unpaid_count' => $data['sales']->count(),
                // String, like every other money field the app reads.
                'total_owed' => number_format($data['totalOwed'], 2, '.', ''),
            ],
        ]);
    }

    /**
     * The credit account to bill this statement against.
     *
     * A customer may hold an account per shop, so prefer an explicit shop_id,
     * then the caller's own shop, and finally fall back to a sole account.
     */
    private function resolveCreditAccount(Request $request, Customer $customer): ?CreditAccount
    {
        $accounts = CreditAccount::where('customer_id', $customer->id)->get();

        if ($accounts->isEmpty()) {
            return null;
        }

        $shopId = $request->integer('shop_id') ?: $request->user()->shop_id;

        return $accounts->firstWhere('shop_id', $shopId)
            ?? ($accounts->count() === 1 ? $accounts->first() : null);
    }

    private function generateCustomerCode(): string
    {
        do {
            $code = 'CUS-'.strtoupper(Str::random(6));
        } while (Customer::where('code', $code)->exists());

        return $code;
    }
}
