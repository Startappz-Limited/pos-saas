@extends('layouts.app')

@section('title', 'Credit Transactions')

@section('content')
	<div>
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h4 class="card-title mb-0">Credit Transactions</h4>
				<a href="{{ route('credit-transactions.overdue') }}" class="btn btn-soft-warning btn-sm">Overdue</a>
			</div>
			<div class="card-body">
				@isset($customer)
					<div class="alert alert-info">Showing transactions for {{ $customer->name }}.</div>
				@endisset

				<form method="GET" action="{{ route('credit-transactions.index') }}" class="row g-3 mb-4">
					<div class="col-md-3">
						<select name="credit_account_id" class="form-select">
							<option value="">All Accounts</option>
							@foreach ($accounts as $account)
								<option value="{{ $account->id }}" @selected((int) request('credit_account_id') === $account->id)>{{ $account->customer?->name ?? $account->uuid }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-3">
						<select name="type" class="form-select">
							<option value="">All Types</option>
							@foreach ($types as $type)
								<option value="{{ $type->value }}" @selected(request('type') === $type->value)>{{ $type->label() }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-3">
						<select name="shop_id" class="form-select">
							<option value="">All Shops</option>
							@foreach ($shops as $shop)
								<option value="{{ $shop->id }}" @selected((int) request('shop_id') === $shop->id)>{{ $shop->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-2"><button class="btn btn-secondary w-100">Filter</button></div>
				</form>

				<div class="table-responsive">
					<table class="table align-middle table-hover mb-0">
						<thead class="table-light"><tr><th>Number</th><th>Customer</th><th>Type</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th><th>Due</th><th>Date</th></tr></thead>
						<tbody>
							@forelse ($transactions as $transaction)
								<tr>
									<td><a href="{{ route('credit-transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a></td>
									<td><a href="{{ route('credit-accounts.show', $transaction->creditAccount) }}" class="text-dark">{{ $transaction->customer?->name ?? '—' }}</a></td>
									<td>{{ $transaction->type->label() }}</td>
									<td class="text-end">{{ $transaction->debit > 0 ? format_currency($transaction->debit) : '—' }}</td>
									<td class="text-end">{{ $transaction->credit > 0 ? format_currency($transaction->credit) : '—' }}</td>
									<td class="text-end">{{ format_currency($transaction->balance_after) }}</td>
									<td>{{ $transaction->due_date?->format('M d, Y') ?? '—' }}</td>
									<td>{{ $transaction->created_at->format('M d, Y') }}</td>
								</tr>
							@empty
								<tr><td colspan="8" class="text-center py-5 text-muted">No credit transactions found.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
				@if ($transactions->hasPages())<div class="mt-4">{{ $transactions->links() }}</div>@endif
			</div>
		</div>
	</div>
@endsection
