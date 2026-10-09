@extends('layouts.app')

@section('title', 'Reports')

@section('content')
	@php
		$filters = $reportData['filters'];
		$summary = $reportData['summary'];
	@endphp

	<div class="card mb-4">
		<div class="card-header border-bottom-dashed">
			<h5 class="card-title mb-0">Detailed Reports & Analytics</h5>
		</div>
		<div class="card-body">
			<form method="GET" action="{{ route('reports.index') }}" class="row g-3">
				<div class="col-12 col-md-3">
					<label class="form-label">Start Date</label>
					<input type="date" class="form-control" name="start_date" value="{{ $filters['start_date'] }}">
				</div>
				<div class="col-12 col-md-3">
					<label class="form-label">End Date</label>
					<input type="date" class="form-control" name="end_date" value="{{ $filters['end_date'] }}">
				</div>
				<div class="col-12 col-md-3">
					<label class="form-label">Shop</label>
					<select class="form-select" name="shop_id">
						<option value="">All shops</option>
						@foreach ($shops as $shop)
							<option value="{{ $shop->id }}" @selected((int) ($filters['shop_id'] ?? 0) === $shop->id)>
								{{ $shop->name }}
							</option>
						@endforeach
					</select>
				</div>
				<div class="col-12 col-md-3">
					<label class="form-label">Sale Status</label>
					<select class="form-select" name="sale_status">
						<option value="all" @selected($filters['sale_status'] === 'all')>All</option>
						<option value="pending" @selected($filters['sale_status'] === 'pending')>Pending</option>
						<option value="completed" @selected($filters['sale_status'] === 'completed')>Completed</option>
						<option value="voided" @selected($filters['sale_status'] === 'voided')>Voided</option>
					</select>
				</div>

				<div class="col-12 col-md-3">
					<label class="form-label">Payment Status</label>
					<select class="form-select" name="payment_status">
						<option value="all" @selected($filters['payment_status'] === 'all')>All</option>
						<option value="paid" @selected($filters['payment_status'] === 'paid')>Paid</option>
						<option value="partial" @selected($filters['payment_status'] === 'partial')>Partial</option>
						<option value="unpaid" @selected($filters['payment_status'] === 'unpaid')>Unpaid</option>
					</select>
				</div>
				<div class="col-12 col-md-3">
					<label class="form-label">Sale Type</label>
					<select class="form-select" name="sale_type">
						<option value="all" @selected($filters['sale_type'] === 'all')>All</option>
						<option value="regular" @selected($filters['sale_type'] === 'regular')>Regular</option>
						<option value="wholesale" @selected($filters['sale_type'] === 'wholesale')>Wholesale</option>
					</select>
				</div>
				<div class="col-12 col-md-3">
					<label class="form-label">Customer Segment</label>
					<select class="form-select" name="customer_segment">
						<option value="all" @selected($filters['customer_segment'] === 'all')>All</option>
						<option value="retail" @selected($filters['customer_segment'] === 'retail')>Retail</option>
						<option value="wholesale" @selected($filters['customer_segment'] === 'wholesale')>Wholesale</option>
					</select>
				</div>
				<div class="col-12 col-md-3">
					<label class="form-label">Expense Status</label>
					<select class="form-select" name="expense_status">
						<option value="all" @selected($filters['expense_status'] === 'all')>All</option>
						@foreach ($expenseStatuses as $status)
							<option value="{{ $status }}" @selected($filters['expense_status'] === $status)>{{ ucfirst($status) }}</option>
						@endforeach
					</select>
				</div>

				<div class="col-12 d-flex gap-2">
					<button type="submit" class="btn btn-primary">
						<iconify-icon icon="solar:filter-bold-duotone" class="align-middle me-1"></iconify-icon>
						Apply Filters
					</button>
					<a href="{{ route('reports.index') }}" class="btn btn-soft-secondary">
						<iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-1"></iconify-icon>
						Reset
					</a>
				</div>
			</form>

			<div class="d-flex flex-wrap gap-2 mt-3">
				<form method="POST" action="{{ route('reports.export', 'dashboard') }}">
					@csrf
					<input type="hidden" name="format" value="csv">
					<input type="hidden" name="start_date" value="{{ $filters['start_date'] }}">
					<input type="hidden" name="end_date" value="{{ $filters['end_date'] }}">
					<input type="hidden" name="shop_id" value="{{ $filters['shop_id'] }}">
					<input type="hidden" name="sale_status" value="{{ $filters['sale_status'] }}">
					<input type="hidden" name="sale_type" value="{{ $filters['sale_type'] }}">
					<input type="hidden" name="customer_segment" value="{{ $filters['customer_segment'] }}">
					<input type="hidden" name="payment_status" value="{{ $filters['payment_status'] }}">
					<input type="hidden" name="expense_status" value="{{ $filters['expense_status'] }}">
					<button type="submit" class="btn btn-success">
						<iconify-icon icon="solar:document-text-bold-duotone" class="align-middle me-1"></iconify-icon>
						Export CSV
					</button>
				</form>

				<form method="POST" action="{{ route('reports.export', 'dashboard') }}">
					@csrf
					<input type="hidden" name="format" value="pdf">
					<input type="hidden" name="start_date" value="{{ $filters['start_date'] }}">
					<input type="hidden" name="end_date" value="{{ $filters['end_date'] }}">
					<input type="hidden" name="shop_id" value="{{ $filters['shop_id'] }}">
					<input type="hidden" name="sale_status" value="{{ $filters['sale_status'] }}">
					<input type="hidden" name="sale_type" value="{{ $filters['sale_type'] }}">
					<input type="hidden" name="customer_segment" value="{{ $filters['customer_segment'] }}">
					<input type="hidden" name="payment_status" value="{{ $filters['payment_status'] }}">
					<input type="hidden" name="expense_status" value="{{ $filters['expense_status'] }}">
					<button type="submit" class="btn btn-danger">
						<iconify-icon icon="solar:file-text-bold-duotone" class="align-middle me-1"></iconify-icon>
						Export PDF
					</button>
				</form>
			</div>
		</div>
	</div>

	<div class="row g-3 mb-4">
		<div class="col-sm-6 col-xl-3">
			<div class="card h-100">
				<div class="card-body">
					<p class="text-muted mb-1">Total Revenue</p>
					<h4 class="mb-0">{{ format_currency($summary['total_revenue']) }}</h4>
				</div>
			</div>
		</div>
		<div class="col-sm-6 col-xl-3">
			<div class="card h-100">
				<div class="card-body">
					<p class="text-muted mb-1">Gross Profit</p>
					<h4 class="mb-0">{{ format_currency($summary['gross_profit']) }}</h4>
					<small class="text-muted">Margin: {{ number_format($summary['gross_margin'], 2) }}%</small>
				</div>
			</div>
		</div>
		<div class="col-sm-6 col-xl-3">
			<div class="card h-100">
				<div class="card-body">
					<p class="text-muted mb-1">Total Expenses</p>
					<h4 class="mb-0">{{ format_currency($summary['expense_total']) }}</h4>
					<small class="text-muted">Count: {{ $summary['total_expenses'] }}</small>
				</div>
			</div>
		</div>
		<div class="col-sm-6 col-xl-3">
			<div class="card h-100">
				<div class="card-body">
					<p class="text-muted mb-1">Net Profit</p>
					<h4 class="mb-0 {{ $summary['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
						{{ format_currency($summary['net_profit']) }}
					</h4>
					<small class="text-muted">Margin: {{ number_format($summary['net_margin'], 2) }}%</small>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mb-4">
		<div class="col-md-6">
			<div class="card h-100">
				<div class="card-header border-bottom-dashed">
					<h5 class="card-title mb-0">Wholesale Performance</h5>
				</div>
				<div class="card-body">
					<div class="d-flex justify-content-between mb-2"><span>Transactions</span><strong>{{ $reportData['wholesale']['transactions'] }}</strong></div>
					<div class="d-flex justify-content-between mb-2"><span>Revenue</span><strong>{{ format_currency($reportData['wholesale']['revenue']) }}</strong></div>
					<div class="d-flex justify-content-between mb-2"><span>Gross Profit</span><strong>{{ format_currency($reportData['wholesale']['gross_profit']) }}</strong></div>
					<div class="d-flex justify-content-between"><span>Margin</span><strong>{{ number_format($reportData['wholesale']['margin'], 2) }}%</strong></div>
				</div>
			</div>
		</div>
		<div class="col-md-6">
			<div class="card h-100">
				<div class="card-header border-bottom-dashed">
					<h5 class="card-title mb-0">Retail Performance</h5>
				</div>
				<div class="card-body">
					<div class="d-flex justify-content-between mb-2"><span>Transactions</span><strong>{{ $reportData['retail']['transactions'] }}</strong></div>
					<div class="d-flex justify-content-between mb-2"><span>Revenue</span><strong>{{ format_currency($reportData['retail']['revenue']) }}</strong></div>
					<div class="d-flex justify-content-between mb-2"><span>Gross Profit</span><strong>{{ format_currency($reportData['retail']['gross_profit']) }}</strong></div>
					<div class="d-flex justify-content-between"><span>Margin</span><strong>{{ number_format($reportData['retail']['margin'], 2) }}%</strong></div>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3">
		<div class="col-12 col-xl-7">
			<div class="card">
				<div class="card-header border-bottom-dashed">
					<h5 class="card-title mb-0">Per Shop Performance (Sales vs Expenses)</h5>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-striped mb-0 align-middle">
							<thead class="table-light">
								<tr>
									<th>Shop</th>
									<th>Revenue</th>
									<th>Gross Profit</th>
									<th>Expenses</th>
									<th>Net Profit</th>
									<th>Net Margin</th>
								</tr>
							</thead>
							<tbody>
								@forelse($reportData['shop_performance'] as $row)
									<tr>
										<td>{{ $row['shop_name'] }}</td>
										<td>{{ format_currency($row['revenue']) }}</td>
										<td>{{ format_currency($row['gross_profit']) }}</td>
										<td>{{ format_currency($row['expense_total']) }}</td>
										<td class="{{ $row['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
											{{ format_currency($row['net_profit']) }}
										</td>
										<td>{{ number_format($row['net_margin'], 2) }}%</td>
									</tr>
								@empty
									<tr>
										<td colspan="6" class="text-center text-muted py-4">No shop data for selected filters.</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-xl-5">
			<div class="card">
				<div class="card-header border-bottom-dashed">
					<h5 class="card-title mb-0">Top Expense Categories</h5>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-striped mb-0 align-middle">
							<thead class="table-light">
								<tr>
									<th>Category</th>
									<th>Count</th>
									<th>Total</th>
								</tr>
							</thead>
							<tbody>
								@forelse($reportData['expenses_by_category'] as $row)
									<tr>
										<td>{{ $row->category_name }}</td>
										<td>{{ $row->total_expenses }}</td>
										<td>{{ format_currency($row->expense_total) }}</td>
									</tr>
								@empty
									<tr>
										<td colspan="3" class="text-center text-muted py-4">No expenses found.</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-xl-6">
			<div class="card">
				<div class="card-header border-bottom-dashed">
					<h5 class="card-title mb-0">Top Products by Margin</h5>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-striped mb-0 align-middle">
							<thead class="table-light">
								<tr>
									<th>Product</th>
									<th>Qty</th>
									<th>Revenue</th>
									<th>Gross Profit</th>
									<th>Margin</th>
								</tr>
							</thead>
							<tbody>
								@forelse($reportData['top_products_by_margin'] as $row)
									<tr>
										<td>{{ $row->product_name }}</td>
										<td>{{ number_format($row->quantity_sold) }}</td>
										<td>{{ format_currency($row->revenue) }}</td>
										<td>{{ format_currency($row->gross_profit) }}</td>
										<td>{{ number_format($row->margin, 2) }}%</td>
									</tr>
								@empty
									<tr>
										<td colspan="5" class="text-center text-muted py-4">No product margin data found.</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
		<div class="col-12 col-xl-6">
			<div class="card">
				<div class="card-header border-bottom-dashed">
					<h5 class="card-title mb-0">Daily Profitability Trend</h5>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive" style="max-height: 440px;">
						<table class="table table-striped mb-0 align-middle">
							<thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
								<tr>
									<th>Date</th>
									<th>Revenue</th>
									<th>Gross Profit</th>
									<th>Expenses</th>
									<th>Net Profit</th>
								</tr>
							</thead>
							<tbody>
								@forelse($reportData['daily_trend'] as $day)
									<tr>
										<td>{{ \Illuminate\Support\Carbon::parse($day['day'])->format('M d, Y') }}</td>
										<td>{{ format_currency($day['revenue']) }}</td>
										<td>{{ format_currency($day['gross_profit']) }}</td>
										<td>{{ format_currency($day['expenses']) }}</td>
										<td class="{{ $day['net_profit'] >= 0 ? 'text-success' : 'text-danger' }}">
											{{ format_currency($day['net_profit']) }}
										</td>
									</tr>
								@empty
									<tr>
										<td colspan="5" class="text-center text-muted py-4">No trend data found.</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>

	<div class="row g-3 mt-1">
		<div class="col-12 col-xl-6">
			<div class="card">
				<div class="card-header border-bottom-dashed">
					<h5 class="card-title mb-0">Recent Sales (Filtered)</h5>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-striped mb-0 align-middle">
							<thead class="table-light">
								<tr>
									<th>Invoice</th>
									<th>Shop</th>
									<th>Total</th>
									<th>Profit</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								@forelse($reportData['recent_sales'] as $sale)
									<tr>
										<td>{{ $sale->invoice_number }}</td>
										<td>{{ $sale->shop?->name ?? '-' }}</td>
										<td>{{ format_currency($sale->total_amount) }}</td>
										<td>{{ format_currency($sale->total_profit) }}</td>
										<td>
											<span class="badge bg-light text-dark">{{ ucfirst($sale->status) }}</span>
											@if ($sale->sale_type === 'wholesale' || $sale->customer?->customer_type === 'wholesale')
												<span class="badge bg-info-subtle text-info">Wholesale</span>
											@endif
										</td>
									</tr>
								@empty
									<tr>
										<td colspan="5" class="text-center text-muted py-4">No recent sales found.</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<div class="col-12 col-xl-6">
			<div class="card">
				<div class="card-header border-bottom-dashed">
					<h5 class="card-title mb-0">Recent Expenses (Filtered)</h5>
				</div>
				<div class="card-body p-0">
					<div class="table-responsive">
						<table class="table table-striped mb-0 align-middle">
							<thead class="table-light">
								<tr>
									<th>Title</th>
									<th>Shop</th>
									<th>Category</th>
									<th>Amount</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								@forelse($reportData['recent_expenses'] as $expense)
									<tr>
										<td>{{ $expense->title }}</td>
										<td>{{ $expense->shop?->name ?? '-' }}</td>
										<td>{{ $expense->category?->name ?? '-' }}</td>
										<td>{{ format_currency($expense->amount) }}</td>
										<td>
											@php
												$statusValue = is_object($expense->status) && isset($expense->status->value)
													? $expense->status->value
													: (string) $expense->status;
											@endphp
											<span class="badge bg-light text-dark">{{ ucfirst($statusValue) }}</span>
										</td>
									</tr>
								@empty
									<tr>
										<td colspan="5" class="text-center text-muted py-4">No recent expenses found.</td>
									</tr>
								@endforelse
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
@endsection
