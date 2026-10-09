@extends('layouts.app')

@section('title', 'Credit Account Statement')

@section('content')
	@php
		$pdfQuery = array_filter($filters, fn ($value) => filled($value));
	@endphp

	<div>
		<div class="mb-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
			<a href="{{ route('credit-accounts.show', $creditAccount) }}" class="btn btn-soft-secondary">Back to Account</a>
			<a href="{{ route('credit-accounts.statement.pdf', ['creditAccount' => $creditAccount] + $pdfQuery) }}" class="btn btn-primary">Download PDF</a>
		</div>

		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
				<div>
					<h4 class="card-title mb-1">Statement: {{ $creditAccount->customer?->name }}</h4>
					<p class="text-muted mb-0">{{ $creditAccount->shop?->name }} · {{ $periodLabel }} · {{ $typeLabel }}</p>
				</div>
			</div>
			<div class="card-body">
				<form method="GET" action="{{ route('credit-accounts.statement', $creditAccount) }}" class="row g-3 mb-4">
					<div class="col-md-3">
						<label for="date_from" class="form-label">From</label>
						<input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] }}" class="form-control @error('date_from') is-invalid @enderror">
						@error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror
					</div>
					<div class="col-md-3">
						<label for="date_to" class="form-label">To</label>
						<input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] }}" class="form-control @error('date_to') is-invalid @enderror">
						@error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
					</div>
					<div class="col-md-3">
						<label for="type" class="form-label">Type</label>
						<select name="type" id="type" class="form-select @error('type') is-invalid @enderror">
							<option value="">All Transactions</option>
							@foreach ($types as $type)
								<option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>{{ $type->label() }}</option>
							@endforeach
						</select>
						@error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
					</div>
					<div class="col-md-3 d-flex align-items-end gap-2">
						<button type="submit" class="btn btn-secondary w-100">Filter</button>
						<a href="{{ route('credit-accounts.statement', $creditAccount) }}" class="btn btn-light w-100">Reset</a>
					</div>
				</form>

				<div class="row g-3 mb-4">
					<div class="col-md-3"><div class="border rounded p-3"><p class="text-muted mb-1">Opening Balance</p><h5 class="mb-0">{{ format_currency($openingBalance) }}</h5></div></div>
					<div class="col-md-3"><div class="border rounded p-3"><p class="text-muted mb-1">Purchases / Debits</p><h5 class="mb-0">{{ format_currency($totalDebits) }}</h5></div></div>
					<div class="col-md-3"><div class="border rounded p-3"><p class="text-muted mb-1">Payments / Credits</p><h5 class="mb-0">{{ format_currency($totalCredits) }}</h5></div></div>
					<div class="col-md-3"><div class="border rounded p-3"><p class="text-muted mb-1">Closing Balance</p><h5 class="mb-0">{{ format_currency($closingBalance) }}</h5></div></div>
				</div>

				<div class="table-responsive">
					<table class="table align-middle table-hover mb-0">
						<thead class="table-light"><tr><th>Date</th><th>Number</th><th>Type</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th><th>Due</th></tr></thead>
						<tbody>
							@forelse ($transactions as $transaction)
								<tr>
									<td>{{ $transaction->created_at->format('M d, Y') }}</td>
									<td><a href="{{ route('credit-transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a></td>
									<td>{{ $transaction->type->label() }}</td>
									<td>{{ $transaction->description ?? '—' }}</td>
									<td class="text-end">{{ $transaction->debit > 0 ? format_currency($transaction->debit) : '—' }}</td>
									<td class="text-end">{{ $transaction->credit > 0 ? format_currency($transaction->credit) : '—' }}</td>
									<td class="text-end">{{ format_currency($transaction->balance_after) }}</td>
									<td>{{ $transaction->due_date?->format('M d, Y') ?? '—' }}</td>
								</tr>
							@empty
								<tr><td colspan="8" class="text-center py-5 text-muted">No transactions found for this statement.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
@endsection