<?php

namespace App\Http\Controllers;

use App\Enums\SupplierStatus;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use App\Services\SupplierService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected SupplierService $supplierService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = $this->supplierService->getAllSuppliers(
            perPage: $request->get('per_page', 15),
            search: $request->get('search'),
            status: $request->get('status') ? SupplierStatus::from($request->get('status')) : null,
            country: $request->get('country')
        );

        $statistics = $this->supplierService->getStatistics();

        return view('suppliers.index', compact('suppliers', 'statistics'));
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = $this->supplierService->createSupplier($request->validated());

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Supplier created successfully.');
    }

    public function show(Supplier $supplier): View
    {
        $this->authorize('view', $supplier);

        $supplier->load(['creator', 'updater']);

        return view('suppliers.show', compact('supplier'));
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.edit', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier = $this->supplierService->updateSupplier($supplier, $request->validated());

        return redirect()->route('suppliers.show', $supplier)
            ->with('success', 'Supplier updated successfully.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $this->supplierService->deleteSupplier($supplier);

        return redirect()->route('suppliers.index')
            ->with('success', 'Supplier deleted successfully.');
    }

    public function activate(Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $this->supplierService->activateSupplier($supplier);

        return back()->with('success', 'Supplier activated successfully.');
    }

    public function deactivate(Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $this->supplierService->deactivateSupplier($supplier);

        return back()->with('success', 'Supplier deactivated successfully.');
    }

    public function suspend(Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $this->supplierService->suspendSupplier($supplier);

        return back()->with('success', 'Supplier suspended successfully.');
    }

    public function blacklist(Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $this->supplierService->blacklistSupplier($supplier);

        return back()->with('success', 'Supplier blacklisted successfully.');
    }

    public function topSuppliers(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $topSuppliers = $this->supplierService->getTopSuppliers(
            limit: $request->get('limit', 10)
        );

        return view('suppliers.top', compact('topSuppliers'));
    }

    public function creditLimitAlerts(Request $request): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = $this->supplierService->getSuppliersNearCreditLimit();

        return view('suppliers.credit-alerts', compact('suppliers'));
    }
}
