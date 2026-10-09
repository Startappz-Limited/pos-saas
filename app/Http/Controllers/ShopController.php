<?php

namespace App\Http\Controllers;

use App\Enums\ShopStatus;
use App\Http\Requests\StoreShopRequest;
use App\Http\Requests\UpdateShopRequest;
use App\Models\Role;
use App\Models\Shop;
use App\Models\User;
use App\Services\Integration\ShopifyService;
use App\Services\Integration\WooCommerceService;
use App\Services\ShopService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShopController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected ShopService $shopService,
        protected WooCommerceService $wooCommerceService,
        protected ShopifyService $shopifyService
    ) {}

    /**
     * Display a listing of shops.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Shop::class);

        $filters = $request->only(['search', 'status', 'manager_id', 'city', 'country']);
        $shops = $this->shopService->getPaginated($filters);
        $statistics = $this->shopService->getStatistics();

        return view('shops.index', compact('shops', 'statistics'));
    }

    /**
     * Show the form for creating a new shop.
     */
    public function create(): View
    {
        $this->authorize('create', Shop::class);

        $managers = User::visibleTo(auth()->user())->select('id', 'name')->whereHas('roles', function ($query) {
            $query->whereIn('name', [Role::ADMIN, 'manager', Role::SUPER_ADMIN]);
        })->get();

        $statuses = ShopStatus::options();

        return view('shops.create', compact('managers', 'statuses'));
    }

    /**
     * Store a newly created shop in storage.
     */
    public function store(StoreShopRequest $request): RedirectResponse
    {
        $this->authorize('create', Shop::class);

        $shop = $this->shopService->create($request->validated());

        return redirect()
            ->route('shops.show', $shop)
            ->with('success', 'Shop created successfully.');
    }

    /**
     * Display the specified shop.
     */
    public function show(Shop $shop): View
    {
        $this->authorize('view', $shop);

        $shop->load(['manager', 'creator', 'updater', 'users']);

        return view('shops.show', compact('shop'));
    }

    /**
     * Show the form for editing the specified shop.
     */
    public function edit(Shop $shop): View
    {
        $this->authorize('update', $shop);

        $shop->load(['users']);

        $managers = User::visibleTo(auth()->user())->select('id', 'name')->whereHas('roles', function ($query) {
            $query->whereIn('name', [Role::ADMIN, 'manager', Role::SUPER_ADMIN]);
        })->get();

        $statuses = ShopStatus::options();

        return view('shops.edit', compact('shop', 'managers', 'statuses'));
    }

    /**
     * Update the specified shop in storage.
     */
    public function update(UpdateShopRequest $request, Shop $shop): RedirectResponse
    {
        $this->authorize('update', $shop);

        $this->shopService->update($shop, $request->validated());

        return redirect()
            ->route('shops.show', $shop)
            ->with('success', 'Shop updated successfully.');
    }

    /**
     * Remove the specified shop from storage.
     */
    public function destroy(Shop $shop): RedirectResponse
    {
        $this->authorize('delete', $shop);

        $this->shopService->delete($shop);

        return redirect()
            ->route('shops.index')
            ->with('success', 'Shop deleted successfully.');
    }

    /**
     * Activate the specified shop.
     */
    public function activate(Shop $shop): RedirectResponse
    {
        $this->authorize('activate', $shop);

        $this->shopService->activate($shop);

        return back()->with('success', 'Shop activated successfully.');
    }

    /**
     * Deactivate the specified shop.
     */
    public function deactivate(Shop $shop): RedirectResponse
    {
        $this->authorize('deactivate', $shop);

        $this->shopService->deactivate($shop);

        return back()->with('success', 'Shop deactivated successfully.');
    }

    /**
     * Suspend the specified shop.
     */
    public function suspend(Shop $shop): RedirectResponse
    {
        $this->authorize('suspend', $shop);

        $this->shopService->suspend($shop);

        return back()->with('success', 'Shop suspended successfully.');
    }

    /**
     * Show the form for managing shop users.
     */
    public function users(Shop $shop): View
    {
        $this->authorize('update', $shop);

        $shop->load('users');
        $availableUsers = User::visibleTo(auth()->user())->whereDoesntHave('shops', function ($query) use ($shop) {
            $query->where('shops.id', $shop->id);
        })->get();

        return view('shops.users', compact('shop', 'availableUsers'));
    }

    /**
     * Update shop users.
     */
    public function updateUsers(Request $request, Shop $shop): RedirectResponse
    {
        $this->authorize('update', $shop);

        $validated = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => [Rule::in(User::visibleTo($request->user())->pluck('id'))],
        ]);

        $this->shopService->assignUsers($shop, $validated['user_ids'] ?? []);

        return redirect()
            ->route('shops.users', $shop)
            ->with('success', 'Shop users updated successfully.');
    }

    /**
     * Test e-commerce platform integration connection
     */
    public function testIntegration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', 'string', 'in:woocommerce,shopify'],
            'credentials' => ['required', 'array'],
        ]);

        $platform = $validated['platform'];
        $credentials = $validated['credentials'];

        try {
            $result = match ($platform) {
                'woocommerce' => $this->wooCommerceService->testConnection($credentials),
                'shopify' => $this->shopifyService->testConnection($credentials),
                default => [
                    'connected' => false,
                    'message' => 'Unsupported platform',
                    'details' => [],
                ],
            };

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'connected' => false,
                'message' => 'An error occurred while testing the connection',
                'details' => ['error' => $e->getMessage()],
            ], 500);
        }
    }
}
