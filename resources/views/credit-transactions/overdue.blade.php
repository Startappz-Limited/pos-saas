@extends('layouts.app')

@section('title', 'Overdue Credit Transactions')

@section('content')
	<div>
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center"><h4 class="card-title mb-0">Overdue Transactions</h4><a href="{{ route('credit-transactions.index') }}" class="btn btn-light btn-sm">All Transactions</a></div>
			<div class="card-body">
				<form method="GET" action="{{ route('credit-transactions.overdue') }}" class="row g-3 mb-4">
					<div class="col-md-4"><select name="shop_id" class="form-select"><option value="">All Shops</option>@foreach ($shops as $shop)<option value="{{ $shop->id }}" @selected((int) request('shop_id') === $shop->id)>{{ $shop->name }}</option>@endforeach</select></div>
					<div class="col-md-2"><button class="btn btn-secondary w-100">Filter</button></div>
				</form>
				<div class="table-responsive">
					<table class="table align-middle table-hover mb-0">
						<thead class="table-light"><tr><th>Number</th><th>Customer</th><th class="text-end">Debit</th><th>Due Date</th><th class="text-end">Days Overdue</th><th>Action</th></tr></thead>
						<tbody>
							@forelse ($transactions as $transaction)
								<tr>
									<td><a href="{{ route('credit-transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a></td>
									<td>{{ $transaction->customer?->name ?? '—' }}</td>
									<td class="text-end">{{ format_currency($transaction->debit) }}</td>
									<td>{{ $transaction->due_date?->format('M d, Y') ?? '—' }}</td>
									<td class="text-end">{{ $transaction->due_date ? (int) $transaction->due_date->diffInDays(today()) : $transaction->days_overdue }}</td>
									<td><a href="{{ route('credit-accounts.show', $transaction->creditAccount) }}" class="btn btn-light btn-sm">Account</a></td>
								</tr>
							@empty
								<tr><td colspan="6" class="text-center py-5 text-muted">No overdue transactions found.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
				@if ($transactions->hasPages())<div class="mt-4">{{ $transactions->links() }}</div>@endif
			</div>
		</div>
	</div>
@endsection
