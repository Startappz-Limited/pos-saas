<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseStatus;
use App\Models\Shop;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('reports.view'), 403);

        [$filters, $allowedShops] = $this->resolveFilters($request);
        $reportData = $this->reportService->getDashboardData($filters);

        return view('reports.index', [
            'reportData' => $reportData,
            'shops' => $allowedShops,
            'expenseStatuses' => collect(ExpenseStatus::cases())->map(fn (ExpenseStatus $status) => $status->value),
        ]);
    }

    protected function resolveFilters(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'shop_id' => ['nullable', 'integer', 'exists:shops,id'],
            'sale_status' => ['nullable', 'in:all,pending,completed,voided'],
            'sale_type' => ['nullable', 'in:all,regular,wholesale'],
            'customer_segment' => ['nullable', 'in:all,retail,wholesale'],
            'payment_status' => ['nullable', 'in:all,paid,partial,unpaid'],
            'expense_status' => ['nullable', 'in:all,draft,pending,approved,rejected,paid,cancelled'],
        ]);

        $allowedShops = $request->user()->can('reports.full-access')
            ? Shop::query()->select('id', 'name')->orderBy('name', 'asc')->get()
            : $request->user()->shops()->select('shops.id', 'shops.name')->orderBy('shops.name', 'asc')->get();

        $allowedShopIds = $request->user()->can('reports.full-access')
            ? []
            : $allowedShops->pluck('id')->all();

        if (! empty($validated['shop_id']) && ! empty($allowedShopIds) && ! in_array((int) $validated['shop_id'], $allowedShopIds, true)) {
            abort(403);
        }

        return [[
            'start_date' => $validated['start_date'] ?? now()->startOfMonth()->toDateString(),
            'end_date' => $validated['end_date'] ?? now()->toDateString(),
            'shop_id' => $validated['shop_id'] ?? null,
            'sale_status' => $validated['sale_status'] ?? 'all',
            'sale_type' => $validated['sale_type'] ?? 'all',
            'customer_segment' => $validated['customer_segment'] ?? 'all',
            'payment_status' => $validated['payment_status'] ?? 'all',
            'expense_status' => $validated['expense_status'] ?? 'all',
            'allowed_shop_ids' => $allowedShopIds,
        ], $allowedShops];
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('reports.index')->with('warning', 'Saved reports creation will be available soon.');
    }

    public function store(Request $request): RedirectResponse
    {
        return redirect()->route('reports.index')->with('warning', 'Saved reports creation will be available soon.');
    }

    public function show(string $report): RedirectResponse
    {
        return redirect()->route('reports.index')->with('warning', 'Detailed saved report pages will be available soon.');
    }

    public function edit(string $report): RedirectResponse
    {
        return redirect()->route('reports.index')->with('warning', 'Saved report editing will be available soon.');
    }

    public function update(Request $request, string $report): RedirectResponse
    {
        return redirect()->route('reports.index')->with('warning', 'Saved report editing will be available soon.');
    }

    public function destroy(string $report): RedirectResponse
    {
        return redirect()->route('reports.index')->with('warning', 'Saved report deletion will be available soon.');
    }

    public function run(string $report): RedirectResponse
    {
        return redirect()->route('reports.index')->with('success', 'Report refresh queued.');
    }

    public function schedule(string $report): RedirectResponse
    {
        return redirect()->route('reports.index')->with('success', 'Report schedule update queued.');
    }

    public function export(Request $request, string $report): Response|StreamedResponse
    {
        abort_unless($request->user()?->can('reports.export') || $request->user()?->can('reports.full-access'), 403);

        [$filters] = $this->resolveFilters($request);
        $validated = $request->validate([
            'format' => ['required', 'in:csv,pdf'],
        ]);

        $reportData = $this->reportService->getDashboardData($filters);
        $format = $validated['format'];
        $timestamp = now()->format('Ymd_His');
        $baseName = "reports_{$report}_{$timestamp}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($reportData): void {
                $output = fopen('php://output', 'w');

                fputcsv($output, ['Detailed Reports Export']);
                fputcsv($output, ['From', $reportData['filters']['start_date'], 'To', $reportData['filters']['end_date']]);
                fputcsv($output, []);

                fputcsv($output, ['Summary']);
                fputcsv($output, ['Metric', 'Value']);
                fputcsv($output, ['Total Revenue', $reportData['summary']['total_revenue']]);
                fputcsv($output, ['Total Cost', $reportData['summary']['total_cost']]);
                fputcsv($output, ['Gross Profit', $reportData['summary']['gross_profit']]);
                fputcsv($output, ['Gross Margin %', $reportData['summary']['gross_margin']]);
                fputcsv($output, ['Expenses', $reportData['summary']['expense_total']]);
                fputcsv($output, ['Net Profit', $reportData['summary']['net_profit']]);
                fputcsv($output, ['Net Margin %', $reportData['summary']['net_margin']]);
                fputcsv($output, []);

                fputcsv($output, ['Shop Performance']);
                fputcsv($output, ['Shop', 'Transactions', 'Revenue', 'Gross Profit', 'Expenses', 'Net Profit', 'Net Margin %']);
                foreach ($reportData['shop_performance'] as $row) {
                    fputcsv($output, [
                        $row['shop_name'],
                        $row['transactions'],
                        $row['revenue'],
                        $row['gross_profit'],
                        $row['expense_total'],
                        $row['net_profit'],
                        $row['net_margin'],
                    ]);
                }
                fputcsv($output, []);

                fputcsv($output, ['Expenses By Category']);
                fputcsv($output, ['Category', 'Count', 'Total']);
                foreach ($reportData['expenses_by_category'] as $row) {
                    fputcsv($output, [$row->category_name, $row->total_expenses, $row->expense_total]);
                }
                fputcsv($output, []);

                fputcsv($output, ['Top Products By Margin']);
                fputcsv($output, ['Product', 'Quantity', 'Revenue', 'Gross Profit', 'Margin %']);
                foreach ($reportData['top_products_by_margin'] as $row) {
                    fputcsv($output, [$row->product_name, $row->quantity_sold, $row->revenue, $row->gross_profit, $row->margin]);
                }
                fputcsv($output, []);

                fputcsv($output, ['Daily Trend']);
                fputcsv($output, ['Day', 'Transactions', 'Revenue', 'Gross Profit', 'Expenses', 'Net Profit']);
                foreach ($reportData['daily_trend'] as $row) {
                    fputcsv($output, [$row['day'], $row['transactions'], $row['revenue'], $row['gross_profit'], $row['expenses'], $row['net_profit']]);
                }

                fclose($output);
            }, $baseName.'.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $pdf = Pdf::loadView('pdf.reports-dashboard', [
            'reportData' => $reportData,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download($baseName.'.pdf');
    }

    public function types(): JsonResponse
    {
        return response()->json([
            'data' => [
                'sales',
                'expenses',
                'profitability',
                'wholesale-margins',
                'shop-performance',
            ],
        ]);
    }

    public function exports(): JsonResponse
    {
        return response()->json([
            'data' => [],
        ]);
    }

    public function download(string $export): RedirectResponse
    {
        return redirect()->route('reports.index')->with('warning', 'Report file download is not ready yet.');
    }
}
