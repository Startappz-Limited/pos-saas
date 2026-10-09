@extends('layouts.app')

@section('title', 'Overdue Credit Accounts')

@section('content')
	<div>
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center"><h4 class="card-title mb-0">Overdue Accounts</h4><a href="{{ route('credit-accounts.index') }}" class="btn btn-light btn-sm">All Accounts</a></div>
			<div class="card-body">
				<form method="GET" action="{{ route('credit-accounts.overdue') }}" class="row g-3 mb-4">
					<div class="col-md-4"><select name="shop_id" class="form-select"><option value="">All Shops</option>@foreach ($shops as $shop)<option value="{{ $shop->id }}" @selected((int) request('shop_id') === $shop->id)>{{ $shop->name }}</option>@endforeach</select></div>
					<div class="col-md-2"><button class="btn btn-secondary w-100">Filter</button></div>
				</form>
				<div class="row mb-4">
					@foreach ($aging as $bucket => $amount)
						<div class="col-md"><div class="border rounded p-3"><small class="text-muted">{{ str_replace('_', ' ', $bucket) }}</small><h5 class="mb-0">{{ format_currency($amount) }}</h5></div></div>
					@endforeach
				</div>
				<div class="table-responsive">
					<table class="table align-middle mb-0">
						<thead class="table-light"><tr><th>Customer</th><th>Shop</th><th class="text-end">Balance</th><th class="text-end">Overdue</th><th>Action</th></tr></thead>
						<tbody>
							@forelse ($overdueAccounts as $account)
								<tr><td>{{ $account->customer?->name }}</td><td>{{ $account->shop?->name }}</td><td class="text-end">{{ format_currency($account->current_balance) }}</td><td class="text-end">{{ format_currency($account->overdueAmount()) }}</td><td><a href="{{ route('credit-accounts.show', $account) }}" class="btn btn-light btn-sm">View</a></td></tr>
							@empty
								<tr><td colspan="5" class="text-center py-5 text-muted">No overdue accounts found.</td></tr>
							@endforelse
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
@endsection
