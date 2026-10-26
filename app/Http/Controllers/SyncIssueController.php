<?php

namespace App\Http\Controllers;

use App\Actions\Customer\MergeCustomers;
use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SyncIssue;
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

        return view('sync-issues.index', [
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
