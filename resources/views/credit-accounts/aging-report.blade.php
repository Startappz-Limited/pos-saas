@extends('layouts.app')

@section('title', 'Credit Aging Report')

@section('content')
	<div>
		<div class="card">
			<div class="card-header d-flex justify-content-between align-items-center"><h4 class="card-title mb-0">Aging Report</h4><a href="{{ route('credit-accounts.index') }}" class="btn btn-light btn-sm">All Accounts</a></div>
			<div class="card-body">
				<form method="GET" action="{{ route('credit-accounts.agingReport') }}" class="row g-3 mb-4">
					<div class="col-md-4"><select name="shop_id" class="form-select"><option value="">All Shops</option>@foreach ($shops as $shop)<option value="{{ $shop->id }}" @selected((int) request('shop_id') === $shop->id)>{{ $shop->name }}</option>@endforeach</select></div>
					<div class="col-md-2"><button class="btn btn-secondary w-100">Filter</button></div>
				</form>
				<div class="row">
					@foreach ($aging as $bucket => $amount)
						<div class="col-md-6 col-xl">
							<div class="card border"><div class="card-body"><p class="text-muted mb-1">{{ str_replace('_', ' ', $bucket) }}</p><h4 class="mb-0">{{ format_currency($amount) }}</h4></div></div>
						</div>
					@endforeach
				</div>
			</div>
		</div>
	</div>
@endsection
