@extends('layouts.app')

@section('title', 'Credit Account Details')

@section('content')
	<div>
		<div class="mb-3"><a href="{{ route('credit-accounts.index') }}" class="btn btn-soft-secondary">Back to Credit Accounts</a></div>

		<div class="row">
			<div class="col-xl-8">
				<div class="card">
					<div class="card-body">
						<div class="d-flex justify-content-between align-items-start gap-3">
							<div>
								<h4 class="mb-1">{{ $creditAccount->customer?->name ?? 'Unknown Customer' }}</h4>
								<div class="d-flex gap-2 flex-wrap">
									<span class="badge bg-{{ $creditAccount->status->color() }}-subtle text-{{ $creditAccount->status->color() }}">{{ $creditAccount->status->label() }}</span>
									<span class="text-muted">{{ $creditAccount->shop?->name }}</span>
								</div>
							</div>
							<div class="d-flex gap-2">
								<a href="{{ route('credit-accounts.transactions', $creditAccount) }}" class="btn btn-soft-info">Ledger</a>
								<a href="{{ route('credit-accounts.statement', $creditAccount) }}" class="btn btn-soft-secondary">Statement</a>
								@can('sendStatement', $creditAccount)
									<button type="button" class="btn btn-soft-success" data-bs-toggle="modal" data-bs-target="#sendStatementModal" @disabled(empty($creditAccount->customer?->phone))>{{ __('Send on WhatsApp') }}</button>
								@endcan
								@can('update', $creditAccount)
									<a href="{{ route('credit-accounts.edit', $creditAccount) }}" class="btn btn-primary">Edit</a>
								@endcan
							</div>
						</div>
					</div>
				</div>

				<div class="row mt-3">
					<div class="col-md-4"><div class="card"><div class="card-body"><p class="text-muted mb-1">Credit Limit</p><h4>{{ format_currency($creditAccount->credit_limit) }}</h4></div></div></div>
					<div class="col-md-4"><div class="card"><div class="card-body"><p class="text-muted mb-1">Current Balance</p><h4 class="{{ $creditAccount->current_balance > 0 ? 'text-warning' : 'text-success' }}">{{ format_currency($creditAccount->current_balance) }}</h4></div></div></div>
					<div class="col-md-4"><div class="card"><div class="card-body"><p class="text-muted mb-1">Available Credit</p><h4>{{ format_currency($creditAccount->available_credit) }}</h4></div></div></div>
				</div>

				<div class="card mt-3">
					<div class="card-header d-flex justify-content-between align-items-center">
						<h5 class="card-title mb-0">Recent Ledger</h5>
						<a href="{{ route('credit-accounts.transactions', $creditAccount) }}" class="btn btn-light btn-sm">View All</a>
					</div>
					<div class="card-body">
						<div class="table-responsive">
							<table class="table align-middle mb-0">
								<thead class="table-light"><tr><th>Number</th><th>Type</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th><th>Date</th></tr></thead>
								<tbody>
									@forelse ($recentTransactions as $transaction)
										<tr>
											<td><a href="{{ route('credit-transactions.show', $transaction) }}">{{ $transaction->transaction_number }}</a></td>
											<td>{{ $transaction->type->label() }}</td>
											<td class="text-end">{{ $transaction->debit > 0 ? format_currency($transaction->debit) : '—' }}</td>
											<td class="text-end">{{ $transaction->credit > 0 ? format_currency($transaction->credit) : '—' }}</td>
											<td class="text-end">{{ format_currency($transaction->balance_after) }}</td>
											<td>{{ $transaction->created_at->format('M d, Y') }}</td>
										</tr>
									@empty
										<tr><td colspan="6" class="text-center py-4 text-muted">No ledger entries yet.</td></tr>
									@endforelse
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

			<div class="col-xl-4">
				@can('create', App\Models\CreditTransaction::class)
					<div class="card">
						<div class="card-header"><h5 class="card-title mb-0">Record Transaction</h5></div>
						<div class="card-body">
							<form action="{{ route('credit-transactions.store') }}" method="POST">
								@csrf
								<input type="hidden" name="credit_account_id" value="{{ $creditAccount->id }}">
								<div class="mb-3">
									<label for="type" class="form-label">Type</label>
									<select name="type" id="type" class="form-select @error('type') is-invalid @enderror">
										@foreach (App\Enums\CreditTransactionType::cases() as $type)
											<option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ $type->label() }}</option>
										@endforeach
									</select>
									@error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
								</div>
								<div class="mb-3">
									<label for="amount" class="form-label">Amount</label>
									<input type="number" step="0.01" min="0.01" name="amount" id="amount" value="{{ old('amount') }}" class="form-control @error('amount') is-invalid @enderror" required>
									@error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
								</div>
								<div class="mb-3">
									<label for="due_date" class="form-label">Due Date</label>
									<input type="date" name="due_date" id="due_date" value="{{ old('due_date') }}" class="form-control @error('due_date') is-invalid @enderror">
									@error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
								</div>
								<div class="mb-3">
									<label for="description" class="form-label">Description</label>
									<textarea name="description" id="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
									@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
								</div>
								<button type="submit" class="btn btn-primary w-100">Record Transaction</button>
							</form>
						</div>
					</div>
				@endcan

				<div class="card mt-3">
					<div class="card-header"><h5 class="card-title mb-0">Account Actions</h5></div>
					<div class="card-body">
						@can('updateLimit', $creditAccount)
							<form action="{{ route('credit-accounts.adjustLimit', $creditAccount) }}" method="POST" class="mb-3">
								@csrf
								<label for="credit_limit" class="form-label">Credit Limit</label>
								<div class="input-group"><input type="number" step="0.01" min="0" name="credit_limit" id="credit_limit" value="{{ $creditAccount->credit_limit }}" class="form-control"><button class="btn btn-secondary" type="submit">Update</button></div>
							</form>
						@endcan

						@if ($creditAccount->is_suspended)
							@can('reactivate', $creditAccount)
								<form action="{{ route('credit-accounts.reactivate', $creditAccount) }}" method="POST">@csrf<button type="submit" class="btn btn-success w-100">Reactivate Account</button></form>
							@endcan
						@else
							@can('suspend', $creditAccount)
								<form action="{{ route('credit-accounts.suspend', $creditAccount) }}" method="POST">
									@csrf
									<textarea name="reason" rows="2" class="form-control mb-2" placeholder="Suspension reason" required></textarea>
									<button type="submit" class="btn btn-warning w-100">Suspend Account</button>
								</form>
							@endcan
						@endif
					</div>
				</div>

				<div class="card mt-3"><div class="card-body"><p class="text-muted mb-1">Overdue Amount</p><h4 class="mb-0">{{ format_currency($overdueAmount) }}</h4></div></div>
			</div>
		</div>
	</div>

	@can('sendStatement', $creditAccount)
		<div class="modal fade" id="sendStatementModal" tabindex="-1" aria-labelledby="sendStatementModalLabel" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="sendStatementModalLabel">{{ __('Send Statement on WhatsApp') }}</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
					</div>
					<form action="{{ route('credit-accounts.sendStatement', $creditAccount) }}" method="POST">
						@csrf
						<div class="modal-body">
							<p class="mb-2">
								{{ __('Send :name a PDF statement of their outstanding invoices?', ['name' => $creditAccount->customer?->name]) }}
							</p>
							<ul class="list-unstyled text-muted mb-0 small">
								<li>{{ __('To') }}: <strong>{{ $creditAccount->customer?->phone ?: __('no phone on file') }}</strong></li>
								<li>{{ __('Outstanding') }}: <strong>{{ format_currency($creditAccount->current_balance) }}</strong></li>
							</ul>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
							<button type="submit" class="btn btn-success">{{ __('Send Statement') }}</button>
						</div>
					</form>
				</div>
			</div>
		</div>
	@endcan
@endsection
