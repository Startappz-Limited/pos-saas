@extends('layouts.app')

@section('title', 'Credit Accounts')

@section('content')
	<div>
		<div class="row">
			<div class="col-md-6 col-xl-3">
				<div class="card"><div class="card-body"><p class="text-muted mb-1">Accounts</p><h4 class="mb-0">{{ $statistics['total'] ?? 0 }}</h4></div></div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card"><div class="card-body"><p class="text-muted mb-1">Outstanding</p><h4 class="mb-0">{{ format_currency($statistics['total_balance'] ?? 0) }}</h4></div></div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card"><div class="card-body"><p class="text-muted mb-1">Available Credit</p><h4 class="mb-0">{{ format_currency($statistics['total_available'] ?? 0) }}</h4></div></div>
			</div>
			<div class="col-md-6 col-xl-3">
				<div class="card"><div class="card-body"><p class="text-muted mb-1">With Balance</p><h4 class="mb-0">{{ $statistics['with_balance'] ?? 0 }}</h4></div></div>
			</div>
		</div>

		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center">
				<h4 class="card-title mb-0">Credit Accounts</h4>
				<div class="d-flex gap-2">
					<a href="{{ route('credit-accounts.overdue') }}" class="btn btn-soft-warning btn-sm">Overdue</a>
					<a href="{{ route('credit-accounts.agingReport') }}" class="btn btn-soft-info btn-sm">Aging</a>
					@can('create', App\Models\CreditAccount::class)
						<a href="{{ route('credit-accounts.create') }}" class="btn btn-primary btn-sm">Create Account</a>
					@endcan
				</div>
			</div>
			<div class="card-body">
				<form method="GET" action="{{ route('credit-accounts.index') }}" class="row g-3 mb-4">
					<div class="col-md-4">
						<input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search customer, email, phone, code">
					</div>
					<div class="col-md-2">
						<select name="status" class="form-select">
							<option value="">All Status</option>
							@foreach ($statuses as $status)
								<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-2">
						<select name="shop_id" class="form-select">
							<option value="">All Shops</option>
							@foreach ($shops as $shop)
								<option value="{{ $shop->id }}" @selected((int) request('shop_id') === $shop->id)>{{ $shop->name }}</option>
							@endforeach
						</select>
					</div>
					<div class="col-md-2">
						<div class="form-check mt-2">
							<input class="form-check-input" type="checkbox" name="has_balance" value="1" id="has_balance" @checked(request()->boolean('has_balance'))>
							<label class="form-check-label" for="has_balance">Has balance</label>
						</div>
					</div>
					<div class="col-md-2">
						<button type="submit" class="btn btn-secondary w-100">Filter</button>
					</div>
				</form>

				<div class="table-responsive">
					<table class="table align-middle table-hover mb-0">
						<thead class="table-light">
							<tr>
								<th>Customer</th>
								<th>Shop</th>
								<th>Status</th>
								<th class="text-end">Limit</th>
								<th class="text-end">Balance</th>
								<th class="text-end">Available</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							@forelse ($accounts as $account)
								<tr>
									<td>
										<a href="{{ route('credit-accounts.show', $account) }}" class="fw-medium text-dark">{{ $account->customer?->name ?? 'Unknown Customer' }}</a>
										<small class="d-block text-muted">{{ $account->customer?->code ?? $account->uuid }}</small>
									</td>
									<td>{{ $account->shop?->name ?? '—' }}</td>
									<td><span class="badge bg-{{ $account->status->color() }}-subtle text-{{ $account->status->color() }}">{{ $account->status->label() }}</span></td>
									<td class="text-end">{{ format_currency($account->credit_limit) }}</td>
									<td class="text-end {{ $account->current_balance > 0 ? 'text-warning' : 'text-success' }}">{{ format_currency($account->current_balance) }}</td>
									<td class="text-end">{{ format_currency($account->available_credit) }}</td>
									<td>
										<div class="d-flex gap-2">
											<a href="{{ route('credit-accounts.show', $account) }}" class="btn btn-light btn-sm">View</a>
											<a href="{{ route('credit-accounts.transactions', $account) }}" class="btn btn-soft-info btn-sm">Ledger</a>
										</div>
									</td>
								</tr>
							@empty
								<tr><td colspan="7" class="text-center py-5 text-muted">No credit accounts found.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>

				@if ($accounts->hasPages())
					<div class="mt-4">{{ $accounts->links() }}</div>
				@endif
			</div>
		</div>
	</div>
@endsection
