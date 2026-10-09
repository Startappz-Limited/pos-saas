<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryCompanyController extends Controller
{
    public function index(): JsonResponse
    {
        $companies = DeliveryCompany::orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $companies,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $company = DeliveryCompany::create([
            ...$validated,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Delivery company created successfully.',
            'data' => $company,
        ], 201);
    }

    public function show(DeliveryCompany $deliveryCompany): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $deliveryCompany,
        ]);
    }

    public function update(Request $request, DeliveryCompany $deliveryCompany): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $deliveryCompany->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Delivery company updated successfully.',
            'data' => $deliveryCompany,
        ]);
    }

    public function destroy(DeliveryCompany $deliveryCompany): JsonResponse
    {
        $deliveryCompany->delete();

        return response()->json([
            'success' => true,
            'message' => 'Delivery company deleted successfully.',
        ]);
    }

    public function toggleActive(DeliveryCompany $deliveryCompany): JsonResponse
    {
        $deliveryCompany->update([
            'is_active' => ! $deliveryCompany->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => $deliveryCompany->is_active ? 'Delivery company activated.' : 'Delivery company deactivated.',
            'data' => $deliveryCompany,
        ]);
    }
}
