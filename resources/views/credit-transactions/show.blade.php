@extends('layouts.app')

@section('title', 'Credit Transaction Details')

@section('content')
	<div>
		<div class="mb-3"><a href="{{ route('credit-transactions.index') }}" class="btn btn-soft-secondary">Back to Transactions</a></div>
		<div class="row">
			<div class="col-lg-8">
				<div class="card">
					<div class="card-header"><h4 class="card-title mb-0">{{ $creditTransaction->transaction_number }}</h4></div>
					<div class="card-body">
						<table class="table table-borderless mb-0">
							<tr><th style="width: 180px;">Customer</th><td><a href="{{ route('credit-accounts.show', $creditTransaction->creditAccount) }}">{{ $creditTransaction->customer?->name ?? '—' }}</a></td></tr>
							<tr><th>Shop</th><td>{{ $creditTransaction->shop?->name ?? '—' }}</td></tr>
							<tr><th>Type</th><td>{{ $creditTransaction->type->label() }}</td></tr>
							<tr><th>Description</th><td>{{ $creditTransaction->description ?? '—' }}</td></tr>
							<tr><th>Notes</th><td>{{ $creditTransaction->notes ?? '—' }}</td></tr>
							<tr><th>Due Date</th><td>{{ $creditTransaction->due_date?->format('M d, Y') ?? '—' }}</td></tr>
							<tr><th>Created By</th><td>{{ $creditTransaction->creator?->name ?? '—' }}</td></tr>
						</table>
					</div>
				</div>
			</div>
			<div class="col-lg-4">
				<div class="card"><div class="card-body"><p class="text-muted mb-1">Debit</p><h4>{{ format_currency($creditTransaction->debit) }}</h4><p class="text-muted mb-1">Credit</p><h4>{{ format_currency($creditTransaction->credit) }}</h4><p class="text-muted mb-1">Balance After</p><h4 class="mb-0">{{ format_currency($creditTransaction->balance_after) }}</h4></div></div>
			</div>
		</div>
	</div>
@endsection
