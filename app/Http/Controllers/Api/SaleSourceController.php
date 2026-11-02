<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SaleSource;
use App\Rules\UniqueForTenant;
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
        abort_unless($request->user()->can('sales.create'), 403, __('This action is unauthorized.'));

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new UniqueForTenant(SaleSource::class, 'name')],
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
