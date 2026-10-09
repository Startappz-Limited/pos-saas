<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        $this->authorize('create', Shop::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Unique within the creator's business, like the web form
            'code' => ['nullable', 'string', 'max:50', Rule::unique('shops', 'code')->where('business_id', $request->user()->currentBusinessId())],
            'description' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
        ]);

        // code and country are NOT NULL but optional for the app (making them
        // required would break it), so fill them rather than let the insert fail
        $validated['code'] ??= $this->generateCode();
        $validated = $this->withoutNulls($validated, ['country']);

        $shop = Shop::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Shop created successfully.',
            'data' => $shop,
        ], 201);
    }

    // index and show stay open to every signed-in user: cashiers (no
    // shops.view) load their shop through them, and ShopAccessScope already
    // limits both to the user's own shops. Writes go through ShopPolicy.
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
        $this->authorize('update', $shop);

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

        // A null country keeps the current one rather than failing the NOT NULL column
        $shop->update($this->withoutNulls($validated, ['country']));

        return response()->json([
            'success' => true,
            'message' => 'Shop updated successfully.',
            'data' => $shop,
        ]);
    }

    public function destroy(Shop $shop): JsonResponse
    {
        $this->authorize('delete', $shop);

        $shop->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shop deleted successfully.',
        ]);
    }

    /**
     * Drop the given keys when they are null, so the column keeps its database
     * default (on create) or its current value (on update).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    private function withoutNulls(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] === null) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * A unique shop code for shops created without one.
     */
    private function generateCode(): string
    {
        do {
            $code = 'SHOP-'.strtoupper(Str::random(6));
        } while (Shop::withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }
}
