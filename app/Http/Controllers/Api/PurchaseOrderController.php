<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $purchaseOrderService) {}

    public function index(Request $request): JsonResponse
    {
        $purchaseOrders = $this->purchaseOrderService->getAllPurchaseOrders(
            perPage: $request->input('per_page', 15),
            search: $request->input('search'),
            status: $request->input('status'),
            supplierId: $request->input('supplier_id'),
            shopId: $request->input('shop_id'),
        );

        return response()->json([
            'success' => true,
            'data' => $purchaseOrders,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderService->createPurchaseOrder(
            $request->validated(),
            $request->user()->id
        );

        $purchaseOrder->load(['supplier', 'shop', 'items.product', 'items.productVariation']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase order created successfully.',
            'data' => $purchaseOrder,
        ], 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['items.product', 'items.productVariation', 'supplier', 'shop', 'stockIntakes', 'creator', 'approver']);

        return response()->json([
            'success' => true,
            'data' => $purchaseOrder,
        ]);
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder = $this->purchaseOrderService->updatePurchaseOrder(
            $purchaseOrder,
            $request->validated(),
            $request->user()->id
        );

        $purchaseOrder->load(['supplier', 'shop', 'items.product', 'items.productVariation']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase order updated successfully.',
            'data' => $purchaseOrder,
        ]);
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->purchaseOrderService->deletePurchaseOrder($purchaseOrder);

        return response()->json([
            'success' => true,
            'message' => 'Purchase order deleted successfully.',
        ]);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->purchaseOrderService->approvePurchaseOrder(
                $purchaseOrder,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Purchase order approved successfully.',
                'data' => $purchaseOrder->fresh(['supplier', 'shop']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function markOrdered(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->purchaseOrderService->markAsOrdered(
                $purchaseOrder,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Purchase order marked as ordered.',
                'data' => $purchaseOrder->fresh(['supplier', 'shop']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        try {
            $this->purchaseOrderService->cancelPurchaseOrder(
                $purchaseOrder,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Purchase order cancelled successfully.',
                'data' => $purchaseOrder->fresh(['supplier', 'shop']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
