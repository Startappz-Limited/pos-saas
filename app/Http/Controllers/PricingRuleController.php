<?php

namespace App\Http\Controllers;

use App\Enums\PricingType;
use App\Http\Requests\StorePricingRuleRequest;
use App\Http\Requests\UpdatePricingRuleRequest;
use App\Models\PricingRule;
use App\Models\Product;
use App\Services\PricingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingRuleController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected PricingService $pricingService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', PricingRule::class);

        $pricingRules = $this->pricingService->getAllPricingRules(
            perPage: $request->get('per_page', 15),
            search: $request->get('search'),
            type: $request->get('type') ? PricingType::from($request->get('type')) : null,
            productId: $request->get('product_id'),
            isActive: $request->has('is_active') ? (bool) $request->get('is_active') : null
        );

        $statistics = $this->pricingService->getStatistics();

        return view('pricing-rules.index', compact('pricingRules', 'statistics'));
    }

    public function create(): View
    {
        $this->authorize('create', PricingRule::class);

        $products = Product::active()->select('id', 'name')->orderBy('name')->get();
        $pricingTypes = PricingType::cases();

        return view('pricing-rules.create', compact('products', 'pricingTypes'));
    }

    public function store(StorePricingRuleRequest $request): RedirectResponse
    {
        $this->authorize('create', PricingRule::class);

        $pricingRule = $this->pricingService->createPricingRule($request->validated());

        return redirect()->route('pricing-rules.show', $pricingRule)
            ->with('success', 'Pricing rule created successfully.');
    }

    public function show(PricingRule $pricingRule): View
    {
        $this->authorize('view', $pricingRule);

        $pricingRule->load(['product', 'customer', 'creator', 'updater']);

        return view('pricing-rules.show', compact('pricingRule'));
    }

    public function edit(PricingRule $pricingRule): View
    {
        $this->authorize('update', $pricingRule);

        $pricingRule->load(['product']);
        $products = Product::active()->select('id', 'name')->orderBy('name')->get();
        $pricingTypes = PricingType::cases();

        return view('pricing-rules.edit', compact('pricingRule', 'products', 'pricingTypes'));
    }

    public function update(UpdatePricingRuleRequest $request, PricingRule $pricingRule): RedirectResponse
    {
        $this->authorize('update', $pricingRule);

        $pricingRule = $this->pricingService->updatePricingRule($pricingRule, $request->validated());

        return redirect()->route('pricing-rules.show', $pricingRule)
            ->with('success', 'Pricing rule updated successfully.');
    }

    public function destroy(PricingRule $pricingRule): RedirectResponse
    {
        $this->authorize('delete', $pricingRule);

        $this->pricingService->deletePricingRule($pricingRule);

        return redirect()->route('pricing-rules.index')
            ->with('success', 'Pricing rule deleted successfully.');
    }

    public function activate(PricingRule $pricingRule): RedirectResponse
    {
        $this->authorize('update', $pricingRule);

        $this->pricingService->activatePricingRule($pricingRule);

        return back()->with('success', 'Pricing rule activated successfully.');
    }

    public function deactivate(PricingRule $pricingRule): RedirectResponse
    {
        $this->authorize('update', $pricingRule);

        $this->pricingService->deactivatePricingRule($pricingRule);

        return back()->with('success', 'Pricing rule deactivated successfully.');
    }

    public function expired(Request $request): View
    {
        $this->authorize('viewAny', PricingRule::class);

        $expiredRules = $this->pricingService->getExpiredPricingRules();

        return view('pricing-rules.expired', compact('expiredRules'));
    }

    public function upcoming(Request $request): View
    {
        $this->authorize('viewAny', PricingRule::class);

        $upcomingRules = $this->pricingService->getUpcomingPricingRules();

        return view('pricing-rules.upcoming', compact('upcomingRules'));
    }
}
