<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashRegisterResource;
use App\Models\CashRegister;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    public function status(): JsonResponse
    {
        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;
        $activeRegister = CashRegister::getActiveRegister($shopId);

        if ($activeRegister) {
            $activeRegister->updateSalesTotals();
            $activeRegister->load(['user', 'closedBy', 'expenses.category']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'has_active_register' => $activeRegister !== null,
                'register' => $activeRegister ? new CashRegisterResource($activeRegister) : null,
            ],
        ]);
    }

    public function index(): JsonResponse
    {
        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;

        $registers = CashRegister::with(['user', 'closedBy'])
            ->where('shop_id', $shopId)
            ->latest('register_date')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => CashRegisterResource::collection($registers),
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'expense_opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string'],
        ]);

        $shopId = auth()->user()->shop_id ?? Shop::first()?->id;
        $activeRegister = CashRegister::getActiveRegister($shopId);

        if ($activeRegister) {
            return response()->json([
                'success' => false,
                'message' => 'A register is already open for today.',
                'data' => ['register' => new CashRegisterResource($activeRegister)],
            ], 422);
        }

        $register = CashRegister::openRegister(
            $shopId,
            $validated['opening_balance'],
            $validated['opening_notes'] ?? null,
            $validated['expense_opening_balance'] ?? 0
        );

        return response()->json([
            'success' => true,
            'message' => 'Register opened successfully.',
            'data' => ['register' => new CashRegisterResource($register)],
        ], 201);
    }

    public function show(CashRegister $cashRegister): JsonResponse
    {
        if ($cashRegister->isOpen()) {
            $cashRegister->updateSalesTotals();
        }

        $cashRegister->load(['user', 'closedBy', 'sales.customer', 'expenses.category']);

        return response()->json([
            'success' => true,
            'data' => new CashRegisterResource($cashRegister),
        ]);
    }

    public function close(Request $request, CashRegister $cashRegister): JsonResponse
    {
        if ($cashRegister->isClosed()) {
            return response()->json([
                'success' => false,
                'message' => 'This register is already closed.',
            ], 422);
        }

        $validated = $request->validate([
            'closing_balance' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string'],
        ]);

        $cashRegister->closeRegister(
            $validated['closing_balance'],
            $validated['closing_notes'] ?? null
        );

        $cashRegister->refresh();
        $cashRegister->load(['user', 'closedBy', 'expenses.category']);

        return response()->json([
            'success' => true,
            'message' => 'Register closed successfully.',
            'data' => new CashRegisterResource($cashRegister),
        ]);
    }
}
