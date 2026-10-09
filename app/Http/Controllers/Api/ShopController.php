<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Shop::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $shops = $query->orderBy('name')->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $shops,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50', 'unique:shops,code'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

        $shop = Shop::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shop created successfully.',
            'data' => $shop,
        ], 201);
    }

    public function show(Shop $shop): JsonResponse
    {
        $shop->load('manager');

        return response()->json([
            'success' => true,
            'data' => $shop,
        ]);
    }

    public function update(Request $request, Shop $shop): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

        $shop->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shop updated successfully.',
            'data' => $shop,
        ]);
    }

    public function destroy(Shop $shop): JsonResponse
    {
        $shop->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shop deleted successfully.',
        ]);
    }
}
