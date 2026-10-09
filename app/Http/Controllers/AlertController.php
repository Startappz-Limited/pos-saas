<?php

namespace App\Http\Controllers;

use App\Enums\AlertCategory;
use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Http\Requests\StoreAlertRequest;
use App\Models\Alert;
use App\Models\Shop;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AlertController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Alert::class);

        $query = Alert::query()
            ->with(['shop:id,name', 'creator:id,name', 'resolver:id,name'])
            ->visibleTo($request->user());

        $this->applyFilters($query, $request);

        return view('alerts.index', $this->listPayload(
            $query->latest()->paginate($request->integer('per_page') ?: 15)->withQueryString(),
            $request
        ));
    }

    /**
     * Unresolved alerts only — the working queue.
     */
    public function unresolved(Request $request): View
    {
        $this->authorize('viewAny', Alert::class);

        $query = Alert::query()
            ->with(['shop:id,name', 'creator:id,name'])
            ->visibleTo($request->user())
            ->unresolved();

        $this->applyFilters($query, $request);

        return view('alerts.index', $this->listPayload(
            $query->latest()->paginate($request->integer('per_page') ?: 15)->withQueryString(),
            $request
        ));
    }

    public function show(Alert $alert): View
    {
        $this->authorize('view', $alert);

        $alert->load(['shop:id,name', 'creator:id,name', 'resolver:id,name', 'alertable']);

        return view('alerts.show', compact('alert'));
    }

    public function store(StoreAlertRequest $request): RedirectResponse
    {
        $alert = Alert::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('alerts.show', $alert)
            ->with('success', __('Alert created successfully.'));
    }

    public function markRead(Alert $alert): RedirectResponse
    {
        $this->authorize('update', $alert);

        if (! $alert->is_read) {
            $alert->markRead();
        }

        return back()->with('success', __('Alert marked as read.'));
    }

    /**
     * Mark every alert the user can see as read.
     */
    public function markAllRead(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Alert::class);

        $count = Alert::query()
            ->visibleTo($request->user())
            ->unread()
            ->update(['is_read' => true, 'read_at' => now()]);

        return back()->with('success', trans_choice(
            '{0}No unread alerts.|{1}:count alert marked as read.|[2,*]:count alerts marked as read.',
            $count,
            ['count' => $count]
        ));
    }

    public function resolve(Request $request, Alert $alert): RedirectResponse
    {
        $this->authorize('update', $alert);

        if ($alert->is_resolved) {
            return back()->with('error', __('Alert already resolved.'));
        }

        $validated = $request->validate([
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $alert->resolve($validated['resolution_notes'] ?? null, $request->user()->id);

        return back()->with('success', __('Alert resolved successfully.'));
    }

    /**
     * Unread count for the topbar badge. Polled by the UI, so it returns JSON
     * rather than a view.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Alert::class);

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => Alert::query()
                    ->visibleTo($request->user())
                    ->unread()
                    ->count(),
            ],
        ]);
    }

    /**
     * Shared filter handling for the index and unresolved listings.
     *
     * @param  Builder<Alert>  $query
     */
    private function applyFilters($query, Request $request): void
    {
        $query
            ->when($request->filled('shop_id'), fn ($q) => $q->where('shop_id', $request->integer('shop_id')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->input('severity')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->boolean('unread_only'), fn ($q) => $q->unread());
    }

    /**
     * @param  LengthAwarePaginator<int, Alert>  $alerts
     * @return array<string, mixed>
     */
    private function listPayload($alerts, Request $request): array
    {
        return [
            'alerts' => $alerts,
            'shops' => Shop::active()->select('id', 'name')->orderBy('name')->get(),
            'types' => AlertType::cases(),
            'severities' => AlertSeverity::cases(),
            'categories' => AlertCategory::cases(),
            'statistics' => $this->statistics($request),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function statistics(Request $request): array
    {
        $stats = Alert::query()
            ->visibleTo($request->user())
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread')
            ->selectRaw('SUM(CASE WHEN is_resolved = 0 THEN 1 ELSE 0 END) as unresolved')
            ->selectRaw("SUM(CASE WHEN severity = 'critical' AND is_resolved = 0 THEN 1 ELSE 0 END) as critical")
            ->first();

        return [
            'total' => (int) $stats->total,
            'unread' => (int) $stats->unread,
            'unresolved' => (int) $stats->unresolved,
            'critical' => (int) $stats->critical,
        ];
    }
}
