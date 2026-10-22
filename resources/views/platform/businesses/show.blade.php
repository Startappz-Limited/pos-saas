@extends('layouts.platform')

@section('title', $business->name)

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('platform.businesses.index') }}">{{ __('Businesses') }}</a></li>
    <li class="breadcrumb-item active">{{ $business->name }}</li>
@endsection

@section('content')
    @include('partials.swal-confirm-script')

    <div class="row">
        <div class="col-xl-8">
            <x-ui-card :title="__('Business')">
                <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                    @include('platform.businesses.partials.status', ['status' => $business->status])
                    @if ($readOnly)
                        <x-ui-badge variant="warning">{{ __('Read-only') }}: {{ \Illuminate\Support\Str::headline($readOnly) }}</x-ui-badge>
                    @endif
                    <span class="text-muted small">{{ $business->uuid }}</span>
                </div>

                @if ($business->isSuspended())
                    <x-ui-alert variant="danger" class="mb-3">
                        {{ __('Suspended :when by :who: :reason', [
                            'when' => $business->suspended_at?->format('d M Y H:i'),
                            'who' => $business->suspendedBy?->name ?? __('a platform operator'),
                            'reason' => $business->suspension_reason,
                        ]) }}
                    </x-ui-alert>
                @endif

                <div class="row text-center g-3">
                    @foreach ([
                        __('Shops') => $business->shops_count,
                        __('Registers') => $business->registers_count,
                        __('Members') => $business->members_count,
                        __('Products') => $business->products_count,
                        __('Sales (30 days)') => $business->sales_30_days,
                        __('AI balance') => $business->ai_balance !== null ? number_format((float) $business->ai_balance, 2) : '—',
                    ] as $label => $value)
                        <div class="col-6 col-md-4">
                            <div class="border rounded p-2">
                                <h4 class="mb-0">{{ $value }}</h4>
                                <p class="text-muted mb-0 small">{{ $label }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-ui-card>

            <x-ui-card :title="__('Members')">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Email') }}</th>
                                <th>{{ __('Roles') }}</th>
                                <th>{{ __('Status here') }}</th>
                                @if (View::exists('platform.businesses.partials.impersonate'))
                                    <th></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($members as $member)
                                <tr>
                                    <td>{{ $member->name }}</td>
                                    <td>{{ $member->email }}</td>
                                    <td>
                                        @foreach ($member->roles as $role)
                                            <x-ui-badge variant="info" soft>{{ $role->displayName() }}</x-ui-badge>
                                        @endforeach
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::headline($member->business_status ?? 'active') }}</td>
                                    @if (View::exists('platform.businesses.partials.impersonate'))
                                        <td>@include('platform.businesses.partials.impersonate')</td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-3">{{ __('No members.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui-card>

            @include('platform.businesses.partials.payments')
        </div>

        <div class="col-xl-4">
            <x-ui-card :title="__('Subscription')">
                @if ($subscription)
                    <p class="mb-1"><strong>{{ $subscription->plan?->name }}</strong> · {{ \Illuminate\Support\Str::headline($subscription->status) }}</p>
                    <p class="text-muted small mb-3">
                        {{ __(':count shop(s) paid for at :price each', ['count' => $subscription->shop_count, 'price' => number_format((float) $subscription->price_at_signup, 2)]) }}<br>
                        {{ __('Ends') }}: {{ $subscription->endsAt()?->format('d M Y H:i') ?? '—' }}
                    </p>
                @else
                    <p class="text-muted">{{ __('No subscription yet (unmetered). Choosing a plan starts its trial.') }}</p>
                @endif

                <form method="POST" action="{{ route('platform.businesses.plan', $business) }}" class="mb-3">
                    @csrf
                    @method('PUT')
                    <label class="form-label" for="plan_id">{{ __('Plan') }}</label>
                    <div class="input-group input-group-sm">
                        <select name="plan_id" id="plan_id" class="form-select @error('plan_id') is-invalid @enderror">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected($subscription?->plan_id === $plan->id)>
                                    {{ $plan->name }} ({{ number_format((float) $plan->price_per_shop, 2) }})
                                </option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary">{{ __('Change plan') }}</button>
                    </div>
                    @error('plan_id')
                        <div class="text-danger small">{{ $message }}</div>
                    @enderror
                    <div class="form-text">{{ __('The new price applies from the next payment; limits and features at once.') }}</div>
                </form>

                @if ($subscription?->status === \App\Models\Subscription::TRIALING)
                    <form method="POST" action="{{ route('platform.businesses.trial', $business) }}">
                        @csrf
                        <label class="form-label" for="days">{{ __('Extend the trial by (days)') }}</label>
                        <div class="input-group input-group-sm">
                            <input type="number" min="1" max="90" name="days" id="days" value="{{ old('days', 14) }}"
                                class="form-control @error('days') is-invalid @enderror">
                            <button type="submit" class="btn btn-outline-primary">{{ __('Extend trial') }}</button>
                        </div>
                        @error('days')
                            <div class="text-danger small">{{ $message }}</div>
                        @enderror
                    </form>
                @endif
            </x-ui-card>

            <x-ui-card :title="__('Suspension')">
                @if ($business->isSuspended())
                    <form method="POST" action="{{ route('platform.businesses.reactivate', $business) }}">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">{{ __('Reactivate') }}</button>
                    </form>
                @elseif (in_array($business->status, ['active', 'pending_verification'], true))
                    <form method="POST" action="{{ route('platform.businesses.suspend', $business) }}" data-swal-confirm
                        data-swal-title="{{ __('Suspend :name?', ['name' => $business->name]) }}"
                        data-swal-text="{{ __('Nobody in this business will be able to work in it, on the web, the app or the API, until it is reactivated.') }}"
                        data-swal-button="{{ __('Suspend') }}">
                        @csrf
                        <input type="hidden" name="confirm" value="0">
                        <label class="form-label" for="reason">{{ __('Reason') }}</label>
                        <textarea name="reason" id="reason" rows="2" maxlength="500" required
                            class="form-control form-control-sm mb-2 @error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                        @error('reason')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @enderror
                        <noscript>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm-suspend">
                                <label class="form-check-label" for="confirm-suspend">{{ __('Yes, suspend this business') }}</label>
                            </div>
                        </noscript>
                        @error('confirm')
                            <div class="text-danger small mb-2">{{ $message }}</div>
                        @enderror
                        <button type="submit" class="btn btn-danger btn-sm">{{ __('Suspend') }}</button>
                    </form>
                @else
                    <p class="text-muted mb-0">{{ __('A closing business can\'t be suspended.') }}</p>
                @endif
            </x-ui-card>
        </div>
    </div>
@endsection
