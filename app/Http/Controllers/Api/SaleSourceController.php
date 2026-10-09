<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaleSource;
use App\Rules\UniqueInBusiness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleSourceController extends Controller
{
    public function index(): JsonResponse
    {
        $sources = SaleSource::active()->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $sources,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', UniqueInBusiness::for('sale_sources', 'name')],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $saleSource = SaleSource::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => true,
            'sort_order' => SaleSource::max('sort_order') + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sale source created successfully.',
            'data' => $saleSource,
        ], 201);
    }
}
