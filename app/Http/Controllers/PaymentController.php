<?php

namespace App\Http\Controllers;

use App\Models\SalePayment;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Read-only views and reports over `sale_payments` — the table the sale flow
 * writes. The separate `payments` table is unused scaffolding; see
 * .claude/skills/pos-domain-rules.
 */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SalePayment::class);

        $query = SalePayment::query()
            ->with(['sale:id,uuid,invoice_number,shop_id,customer_id', 'sale.shop:id,name', 'sale.customer:id,name', 'receiver:id,name'])
            ->visibleTo($request->user());

        $this->applyFilters($query, $request);

        $payments = $query->latest('paid_at')->paginate($request->integer('per_page') ?: 15)->withQueryString();

        return view('payments.index', [
            'payments' => $payments,
            'shops' => Shop::active()->select('id', 'name')->orderBy('name')->get(),
            'methods' => $this->availableMethods($request),
            'statistics' => $this->statistics($request),
            'filters' => $this->filters($request),
        ]);
    }

    public function show(SalePayment $payment): View
    {
        $this->authorize('view', $payment);

        $payment->load(['sale.shop:id,name', 'sale.customer:id,name,phone', 'receiver:id,name']);

        return view('payments.show', compact('payment'));
    }

    /**
     * Payment totals per day for the selected window.
     */
    public function dailyReport(Request $request): View
    {
        $this->authorize('viewAny', SalePayment::class);

        [$from, $to] = $this->dateWindow($request);

        $query = SalePayment::query()->visibleTo($request->user());
        $this->applyFilters($query, $request);

        $rows = $query
            // Range on the raw column so the ['sale_id','paid_at'] index stays usable —
            // whereDate() would wrap paid_at in DATE() and force a scan.
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('DATE(paid_at) as day')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('day')
            ->orderByDesc('day')
            ->get();

        return view('payments.reports.daily', [
            'rows' => $rows,
            'shops' => Shop::active()->select('id', 'name')->orderBy('name')->get(),
            'filters' => $this->filters($request) + ['from' => $from, 'to' => $to],
            'totals' => [
                'payment_count' => (int) $rows->sum('payment_count'),
                'total_amount' => (float) $rows->sum('total_amount'),
            ],
        ]);
    }

    /**
     * Payment totals per method for the selected window.
     */
    public function methodsReport(Request $request): View
    {
        $this->authorize('viewAny', SalePayment::class);

        [$from, $to] = $this->dateWindow($request);

        $query = SalePayment::query()->visibleTo($request->user());
        $this->applyFilters($query, $request);

        $rows = $query
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('payment_method')
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->get();

        $grandTotal = (float) $rows->sum('total_amount');

        return view('payments.reports.methods', [
            'rows' => $rows,
            'shops' => Shop::active()->select('id', 'name')->orderBy('name')->get(),
            'filters' => $this->filters($request) + ['from' => $from, 'to' => $to],
            'totals' => [
                'payment_count' => (int) $rows->sum('payment_count'),
                'total_amount' => $grandTotal,
            ],
        ]);
    }

    /**
     * @param  Builder<SalePayment>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when(
                $request->filled('shop_id'),
                fn (Builder $q) => $q->whereHas('sale', fn (Builder $s) => $s->where('shop_id', $request->integer('shop_id')))
            )
            ->when(
                $request->filled('payment_method'),
                fn (Builder $q) => $q->where('payment_method', $request->input('payment_method'))
            )
            ->when(
                $request->filled('search'),
                fn (Builder $q) => $q->where(function (Builder $inner) use ($request): void {
                    $term = $request->input('search');

                    $inner->where('payment_number', 'like', $term.'%')
                        ->orWhere('reference', 'like', $term.'%')
                        ->orWhereHas('sale', fn (Builder $s) => $s->where('invoice_number', 'like', $term.'%'));
                })
            );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dateWindow(Request $request): array
    {
        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->input('from'))->startOfDay()
            : CarbonImmutable::now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? CarbonImmutable::parse($request->input('to'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        return $from->lessThanOrEqualTo($to) ? [$from, $to] : [$to->startOfDay(), $from->endOfDay()];
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'shop_id' => $request->input('shop_id'),
            'payment_method' => $request->input('payment_method'),
            'search' => $request->input('search'),
        ];
    }

    /**
     * Distinct methods actually present in the visible data, so the filter never
     * offers an option that returns nothing.
     *
     * @return array<int, string>
     */
    private function availableMethods(Request $request): array
    {
        return SalePayment::query()
            ->visibleTo($request->user())
            ->select('payment_method')
            ->distinct()
            ->orderBy('payment_method')
            ->pluck('payment_method')
            ->all();
    }

    /**
     * @return array<string, float|int>
     */
    private function statistics(Request $request): array
    {
        $query = SalePayment::query()->visibleTo($request->user());
        $this->applyFilters($query, $request);

        $stats = $query
            ->selectRaw('COUNT(*) as payment_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN DATE(paid_at) = ? THEN amount ELSE 0 END), 0) as today_amount', [
                CarbonImmutable::now()->toDateString(),
            ])
            ->first();

        return [
            'payment_count' => (int) $stats->payment_count,
            'total_amount' => (float) $stats->total_amount,
            'today_amount' => (float) $stats->today_amount,
        ];
    }
}
