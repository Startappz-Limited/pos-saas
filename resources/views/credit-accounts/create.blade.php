@extends('layouts.app')

@section('title', 'Create Credit Account')

@section('content')
	<div>
		<div class="mb-3">
			<a href="{{ route('credit-accounts.index') }}" class="btn btn-soft-secondary">Back to Credit Accounts</a>
		</div>

		<form action="{{ route('credit-accounts.store') }}" method="POST">
			@csrf
			<div class="row">
				<div class="col-lg-8">
					<div class="card">
						<div class="card-header"><h5 class="card-title mb-0">Account Details</h5></div>
						<div class="card-body">
							<div class="mb-3">
								<label for="customer_id" class="form-label">Customer <span class="text-danger">*</span></label>
								<select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
									<option value="">Select customer</option>
									@foreach ($customers as $customer)
										<option value="{{ $customer->id }}" data-shop="{{ $customer->shop_id }}" @selected((int) old('customer_id') === $customer->id)>{{ $customer->name }} {{ $customer->code ? '(' . $customer->code . ')' : '' }}</option>
									@endforeach
								</select>
								@error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
							</div>

							<div class="mb-3">
								<label for="shop_id" class="form-label">Shop <span class="text-danger">*</span></label>
								<select name="shop_id" id="shop_id" class="form-select @error('shop_id') is-invalid @enderror" required>
									<option value="">Select shop</option>
									@foreach ($shops as $shop)
										<option value="{{ $shop->id }}" @selected((int) old('shop_id') === $shop->id)>{{ $shop->name }}</option>
									@endforeach
								</select>
								@error('shop_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
							</div>

							<div class="row">
								<div class="col-md-4 mb-3">
									<label for="credit_limit" class="form-label">Credit Limit</label>
									<input type="number" step="0.01" min="0" name="credit_limit" id="credit_limit" value="{{ old('credit_limit', 0) }}" class="form-control @error('credit_limit') is-invalid @enderror" required>
									@error('credit_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
								</div>
								<div class="col-md-4 mb-3">
									<label for="payment_terms_days" class="form-label">Payment Terms</label>
									<input type="number" min="0" name="payment_terms_days" id="payment_terms_days" value="{{ old('payment_terms_days', 30) }}" class="form-control @error('payment_terms_days') is-invalid @enderror" required>
									@error('payment_terms_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
								</div>
								<div class="col-md-4 mb-3">
									<label for="grace_period_days" class="form-label">Grace Period</label>
									<input type="number" min="0" name="grace_period_days" id="grace_period_days" value="{{ old('grace_period_days', 7) }}" class="form-control @error('grace_period_days') is-invalid @enderror" required>
									@error('grace_period_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
								</div>
							</div>
						</div>
					</div>
				</div>

				<div class="col-lg-4">
					<div class="card">
						<div class="card-header"><h5 class="card-title mb-0">Status</h5></div>
						<div class="card-body">
							<select name="status" class="form-select @error('status') is-invalid @enderror">
								@foreach ($statuses as $status)
									<option value="{{ $status->value }}" @selected(old('status', App\Enums\CreditAccountStatus::ACTIVE->value) === $status->value)>{{ $status->label() }}</option>
								@endforeach
							</select>
							@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>

					<div class="card mt-3"><div class="card-body"><button type="submit" class="btn btn-primary w-100 mb-2">Create Account</button><a href="{{ route('credit-accounts.index') }}" class="btn btn-light w-100">Cancel</a></div></div>
				</div>
			</div>
		</form>
	</div>
@endsection
