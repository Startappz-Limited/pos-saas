<?php

namespace App\Http\Controllers;

use App\Actions\Customer\MergeCustomers;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\DeliveryCompany;
use App\Models\Sale;
use App\Models\SaleSource;
use App\Models\SyncIssue;
use App\Models\User;
use App\Support\Sync\HeldItems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Till sync review (Phase 7, decision 4.9): what the tills' pushes turned up
 * (invoice gaps and repeats, possible duplicate customers, sales below zero
 * stock or with a future time) for an owner or manager to check, keep or
 * merge. Each resolution is audited.
 */
class SyncIssueController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SyncIssue::class);

        $showResolved = $request->query('show') === 'resolved';
        $issues = SyncIssue::query()
            ->with(['register:id,name,code', 'shop:id,name', 'resolvedBy:id,name'])
            ->when($showResolved, fn ($query) => $query->whereNotNull('resolved_at')->latest('resolved_at'), fn ($query) => $query->open()->latest())
            ->paginate(25)
            ->withQueryString();

        // The records the issues name, read once for the page.
        $customerUuids = $issues->getCollection()->where('type', SyncIssue::POSSIBLE_DUPLICATE_CUSTOMER)
            ->flatMap(fn (SyncIssue $issue) => [$issue->details['customer'] ?? null, $issue->details['duplicate_of'] ?? null])->filter();
        $customers = Customer::query()->withTrashed()->withCount('sales')->whereIn('uuid', $customerUuids)->get()->keyBy(fn (Customer $customer) => (string) $customer->uuid);
        $sales = Sale::query()->whereIn('uuid', $issues->getCollection()->pluck('details.sale')->merge($issues->getCollection()->pluck('details.existing_sale'))->filter())
            ->get(['id', 'uuid', 'invoice_number'])->keyBy(fn (Sale $sale) => (string) $sale->uuid);

        // Held till items (stage 10, step 5c): what each can be resolved by, and the lists a correction picks from.
        $heldItems = app(HeldItems::class);
        $held = $issues->getCollection()->where('type', SyncIssue::HELD_ITEM)->mapWithKeys(fn (SyncIssue $issue) => [$issue->id => $heldItems->options($issue)]);
        $needsLists = $held->contains(fn (array $options) => $options['corrections'] !== []);

        return view('sync-issues.index', [
            'held' => $held,
            'lists' => $needsLists ? [
                'customers' => Customer::query()->orderBy('name')->limit(500)->get(['id', 'name', 'phone', 'customer_type']),
                'sources' => SaleSource::query()->orderBy('name')->get(['id', 'name']),
                'deliveryCompanies' => DeliveryCompany::query()->orderBy('name')->get(['id', 'name']),
                'staff' => User::query()->inCurrentBusiness()->orderBy('name')->get(['id', 'uuid', 'name']),
            ] : [],
            'issues' => $issues,
            'customers' => $customers,
            'sales' => $sales,
            'showResolved' => $showResolved,
            'openCount' => SyncIssue::query()->open()->count(),
        ]);
    }

    /**
     * Marks an issue as looked at (or, for a possible duplicate, keeps both).
     */
    public function resolve(SyncIssue $syncIssue, Request $request): RedirectResponse
    {
        $this->authorize('resolve', $syncIssue);

        $resolution = $syncIssue->type === SyncIssue::POSSIBLE_DUPLICATE_CUSTOMER ? 'kept' : 'checked';
        $syncIssue->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id, 'resolution' => $resolution]);

        return back()->with('success', $resolution === 'kept' ? __('Both customers kept.') : __('Marked as checked.'));
    }

    /**
     * Applies a held till item, with the corrections the review offers
     * (stage 10, step 5c); needs `sync-issues.accept`.
     */
    public function accept(SyncIssue $syncIssue, Request $request, HeldItems $held): RedirectResponse
    {
        abort_unless($request->user()->can('sync-issues.accept') && $syncIssue->type === SyncIssue::HELD_ITEM, 403);

        $corrections = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'walk_in_customer_name' => ['nullable', 'string', 'max:255'],
            'walk_in_customer_phone' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['nullable', 'in:cash,card,bank_transfer,mobile_money,credit'],
            'source_id' => ['nullable', 'exists:sale_sources,id'],
            'delivery_location' => ['nullable', 'string', 'max:1000'],
            'delivery_company_id' => ['nullable', 'exists:delivery_companies,id'],
            'customer_tax_pin' => ['nullable', 'string', 'max:32'],
            'cashier' => ['nullable', 'uuid'],
            'variations' => ['nullable', 'array'],
            'variations.*' => ['nullable', 'exists:product_variations,id'],
        ]);

        try {
            $held->accept($syncIssue, $request->user(), array_filter($corrections, fn ($value) => $value !== null));
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Accepted and applied. The till clears it at its next sync.'));
    }

    /**
     * Discards a held till item with a reason; a sale or payment needs `sales.void`.
     */
    public function discard(SyncIssue $syncIssue, Request $request, HeldItems $held): RedirectResponse
    {
        abort_unless($syncIssue->type === SyncIssue::HELD_ITEM && $held->mayDiscard($request->user(), $syncIssue), 403);
        $validated = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $held->discard($syncIssue, $request->user(), $validated['reason']);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Discarded. The till clears it at its next sync.'));
    }

    /**
     * "Discard and record as a new web sale" for a sale whose till number was taken.
     */
    public function rerung(SyncIssue $syncIssue, Request $request, HeldItems $held): RedirectResponse
    {
        abort_unless($request->user()->can('sync-issues.accept') && $syncIssue->type === SyncIssue::HELD_ITEM, 403);

        try {
            $outcome = $held->rerung($syncIssue, $request->user());
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        $sale = Sale::query()->where('uuid', $outcome['new_sale'] ?? '')->first();

        return $sale !== null
            ? redirect()->route('sales.show', $sale)->with('success', __('Recorded as web sale :invoice. The till\'s item is discarded.', ['invoice' => $sale->invoice_number]))
            : back()->with('success', __('Recorded as a new web sale.'));
    }

    /**
     * Merges a possible duplicate into the customer it matched.
     */
    public function merge(SyncIssue $syncIssue, Request $request, MergeCustomers $merge): RedirectResponse
    {
        $this->authorize('resolve', $syncIssue);
        abort_unless($syncIssue->type === SyncIssue::POSSIBLE_DUPLICATE_CUSTOMER, 404);

        $duplicate = Customer::query()->where('uuid', $syncIssue->details['customer'] ?? null)->first();
        $keep = Customer::query()->where('uuid', $syncIssue->details['duplicate_of'] ?? null)->first();

        if ($duplicate === null || $keep === null) {
            return back()->with('error', __('One of these customers no longer exists. Mark the issue as checked instead.'));
        }

        try {
            $merge->execute($duplicate, $keep);
        } catch (BusinessRuleException $e) {
            return back()->with('error', $e->getMessage());
        }

        $syncIssue->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id, 'resolution' => 'merged']);

        return back()->with('success', __(':duplicate was merged into :keep.', ['duplicate' => $duplicate->name, 'keep' => $keep->name]));
    }
}
