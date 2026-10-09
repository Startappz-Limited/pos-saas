@extends('layouts.app')

@section('title', 'Edit Credit Account')

@section('content')
	<div>
		<div class="mb-3"><a href="{{ route('credit-accounts.show', $creditAccount) }}" class="btn btn-soft-secondary">Back to Account</a></div>

		<form action="{{ route('credit-accounts.update', $creditAccount) }}" method="POST">
			@csrf
			@method('PUT')
			<div class="row">
				<div class="col-lg-8">
					<div class="card">
						<div class="card-header"><h5 class="card-title mb-0">{{ $creditAccount->customer?->name }}</h5></div>
						<div class="card-body">
							<div class="row">
								<div class="col-md-4 mb-3">
									<label for="credit_limit" class="form-label">Credit Limit</label>
									<input type="number" step="0.01" min="0" name="credit_limit" id="credit_limit" value="{{ old('credit_limit', $creditAccount->credit_limit) }}" class="form-control @error('credit_limit') is-invalid @enderror" required>
									@error('credit_limit')<div class="invalid-feedback">{{ $message }}</div>@enderror
								</div>
								<div class="col-md-4 mb-3">
									<label for="payment_terms_days" class="form-label">Payment Terms</label>
									<input type="number" min="0" name="payment_terms_days" id="payment_terms_days" value="{{ old('payment_terms_days', $creditAccount->payment_terms_days) }}" class="form-control @error('payment_terms_days') is-invalid @enderror" required>
									@error('payment_terms_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
								</div>
								<div class="col-md-4 mb-3">
									<label for="grace_period_days" class="form-label">Grace Period</label>
									<input type="number" min="0" name="grace_period_days" id="grace_period_days" value="{{ old('grace_period_days', $creditAccount->grace_period_days) }}" class="form-control @error('grace_period_days') is-invalid @enderror" required>
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
									<option value="{{ $status->value }}" @selected(old('status', $creditAccount->status->value) === $status->value)>{{ $status->label() }}</option>
								@endforeach
							</select>
							@error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					<div class="card mt-3"><div class="card-body"><button type="submit" class="btn btn-primary w-100 mb-2">Update Account</button><a href="{{ route('credit-accounts.show', $creditAccount) }}" class="btn btn-light w-100">Cancel</a></div></div>
				</div>
			</div>
		</form>
	</div>
@endsection
