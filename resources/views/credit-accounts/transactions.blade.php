@extends('layouts.app')

@section('title', 'Credit Account Ledger')

@section('content')
	<div>
		<div class="mb-3"><a href="{{ route('credit-accounts.show', $creditAccount) }}" class="btn btn-soft-secondary">Back to Account</a></div>
		<div class="card">
			<div class="card-header"><h4 class="card-title mb-0">Ledger: {{ $creditAccount->customer?->name }}</h4></div>
			<div class="card-body">
				<div class="table-responsive">
					<table class="table align-middle table-hover mb-0">
						<thead class="table-light"><tr><th>Number</th><th>Type</th><th>Description</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th><th>Due</th><th>Date</th></tr></thead>
						<tbody>
							@forelse ($transactions as $transaction)
								<tr>
									<td><a href="{{ route('credit-transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a></td>
									<td>{{ $transaction->type->label() }}</td>
									<td>{{ $transaction->description ?? '—' }}</td>
									<td class="text-end">{{ $transaction->debit > 0 ? format_currency($transaction->debit) : '—' }}</td>
									<td class="text-end">{{ $transaction->credit > 0 ? format_currency($transaction->credit) : '—' }}</td>
									<td class="text-end">{{ format_currency($transaction->balance_after) }}</td>
									<td>{{ $transaction->due_date?->format('M d, Y') ?? '—' }}</td>
									<td>{{ $transaction->created_at->format('M d, Y') }}</td>
								</tr>
							@empty
								<tr><td colspan="8" class="text-center py-5 text-muted">No transactions found.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
				@if ($transactions->hasPages())<div class="mt-4">{{ $transactions->links() }}</div>@endif
			</div>
		</div>
	</div>
@endsection
