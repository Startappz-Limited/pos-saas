@extends('layouts.app')

@section('title', 'Billing')

@section('content')
    @php
        $plan = $subscription?->plan;
        $reason = $entitlements->readOnlyReason();
    @endphp

    @if ($reason)
        <div class="alert alert-danger">
            @if ($reason === 'shops_over_quota')
                {{ __('You have more shops than you pay for, so every shop is read-only. Delete shops or pay for more below.') }}
            @else
                {{ __('Your subscription has lapsed, so this business is read-only: you can view and export your data, but not make changes.') }}
                @if ($entitlements->graceEndsAt())
                    {{ __('Renew by :date or the business is closed and its data erased.', ['date' => $entitlements->graceEndsAt()->toFormattedDayDateString()]) }}
                @endif
            @endif
        </div>
    @endif

    <div class="row">
        <div class="col-lg-7">
            <x-ui-card title="{{ __('Subscription') }}" class="mb-3">
                @if (! $subscription)
                    <p class="text-muted mb-0">{{ __('This business has no subscription.') }}</p>
                @else
                    <dl class="row mb-3">
                        <dt class="col-sm-4">{{ __('Plan') }}</dt>
                        <dd class="col-sm-8">{{ $plan->name }} — KES {{ number_format((float) $subscription->price_at_signup) }} {{ __('per shop per month') }}</dd>
                        <dt class="col-sm-4">{{ __('Status') }}</dt>
                        <dd class="col-sm-8">
                            @if ($subscription->isInGoodStanding())
                                <span class="badge bg-success-subtle text-success">{{ $subscription->status === 'trialing' ? __('Trial') : __('Active') }}</span>
                                {{ __('until :date', ['date' => $subscription->endsAt()->toFormattedDayDateString()]) }}
                            @else
                                <span class="badge bg-danger-subtle text-danger">{{ __('Lapsed') }}</span>
                            @endif
                        </dd>
                        @if ($subscription->status === 'trialing')
                            <dt class="col-sm-4">{{ __('Trial ends') }}</dt>
                            <dd class="col-sm-8">{{ $subscription->trial_ends_at->toFormattedDayDateString() }}</dd>
                        @elseif ($subscription->trial_ends_at && $subscription->trial_ends_at->isFuture())
                            <dt class="col-sm-4">{{ __('Paid period starts') }}</dt>
                            <dd class="col-sm-8">{{ $subscription->trial_ends_at->toFormattedDayDateString() }} ({{ __('the rest of your trial is kept') }})</dd>
                        @endif
                        <dt class="col-sm-4">{{ __('Shops paid for') }}</dt>
                        <dd class="col-sm-8">{{ $subscription->shop_count }} ({{ __(':count in use', ['count' => $usage['shops']]) }})</dd>
                        <dt class="col-sm-4">{{ __('Each month') }}</dt>
                        <dd class="col-sm-8">KES {{ number_format($subscription->monthlyTotal(), 2) }}</dd>
                        <dt class="col-sm-4">{{ __('Staff / products') }}</dt>
                        <dd class="col-sm-8">
                            {{ $usage['staff'] }} / {{ $entitlements->limit(\App\Enums\Limit::Staff) ?? __('unlimited') }},
                            {{ $usage['products'] }} / {{ $entitlements->limit(\App\Enums\Limit::Products) ?? __('unlimited') }}
                        </dd>
                    </dl>

                    @if ($onlinePayment)
                        <form method="POST" action="{{ route('billing.pay') }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-sm-5">
                                <label class="form-label" for="pay_phone">{{ __('M-Pesa phone') }}</label>
                                <input type="text" name="phone" id="pay_phone" class="form-control" placeholder="07XX XXX XXX" required>
                            </div>
                            <div class="col-sm-3">
                                <label class="form-label" for="months">{{ __('Months') }}</label>
                                <input type="number" name="months" id="months" class="form-control" min="1" max="12" value="1">
                            </div>
                            <div class="col-sm-4">
                                <x-ui-button variant="primary" type="submit" class="w-100">{{ __('Pay with M-Pesa') }}</x-ui-button>
                            </div>
                        </form>
                    @else
                        <p class="text-muted mb-0">{{ __('Pay the platform by M-Pesa and we record it against your business. Online payment is coming soon.') }}</p>
                    @endif

                    @if ($subscription->status === 'trialing' || $plan->is_trial_plan)
                        <hr>
                        <form method="POST" action="{{ route('billing.plan') }}" id="plan-form">
                            @csrf
                            <h6>{{ __('Change plan') }}</h6>
                            <p class="small text-muted">{{ __('Switching plan takes effect now and never changes when your trial ends. Pay after you have chosen.') }}</p>
                            @if (session('plan_warnings'))
                                <div class="alert alert-warning">
                                    <ul class="mb-2">
                                        @foreach (session('plan_warnings') as $warning)
                                            <li>{{ $warning }}</li>
                                        @endforeach
                                    </ul>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="confirm" value="1" id="plan_confirm">
                                        <label class="form-check-label" for="plan_confirm">{{ __('I understand, change my plan anyway') }}</label>
                                    </div>
                                </div>
                            @endif
                            <div class="row g-2 align-items-end">
                                <div class="col-sm-5">
                                    <label class="form-label" for="plan_key">{{ __('Plan') }}</label>
                                    <select name="plan" id="plan_key" class="form-select">
                                        @if ($plan->is_trial_plan)
                                            <option value="{{ $plan->key }}" selected>{{ $plan->name }}</option>
                                        @endif
                                        @foreach ($tiers as $tier)
                                            <option value="{{ $tier->key }}" @selected(old('plan', $plan->key) === $tier->key)>{{ $tier->name }} — KES {{ number_format((float) $tier->price_per_shop) }} / {{ __('shop / month') }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <label class="form-label" for="plan_shops">{{ __('Shops') }}</label>
                                    <input type="number" name="shop_count" id="plan_shops" class="form-control" min="1" max="50" value="{{ old('shop_count', $subscription->shop_count) }}">
                                </div>
                                <div class="col-sm-4">
                                    <x-ui-button variant="outline-primary" type="submit">{{ __('Change plan') }}</x-ui-button>
                                </div>
                            </div>
                        </form>
                    @endif

                    <hr>
                    <form method="POST" action="{{ route('billing.shops') }}" class="row g-2 align-items-end">
                        @csrf
                        <div class="col-sm-6">
                            <label class="form-label" for="shop_count">{{ __('Shops to pay for') }}</label>
                            <input type="number" name="shop_count" id="shop_count" class="form-control" min="1" max="200" value="{{ $subscription->shop_count }}">
                        </div>
                        <div class="col-sm-6">
                            <x-ui-button variant="outline-primary" type="submit">{{ __('Update shops') }}</x-ui-button>
                        </div>
                    </form>
                @endif
            </x-ui-card>

            <x-ui-card title="{{ __('Payments') }}">
                @forelse ($payments as $payment)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div>
                            {{ $payment->purpose === 'ai_topup' ? __('AI top-up') : __('Subscription') }}
                            — KES {{ number_format((float) $payment->amount, 2) }}
                            <span class="small text-muted">{{ $payment->created_at->toDayDateTimeString() }}{{ $payment->mpesa_receipt ? ' · '.$payment->mpesa_receipt : '' }}</span>
                        </div>
                        <div>
                            <span class="badge bg-{{ $payment->status === 'confirmed' ? 'success' : ($payment->status === 'failed' ? 'danger' : 'warning') }}-subtle text-{{ $payment->status === 'confirmed' ? 'success' : ($payment->status === 'failed' ? 'danger' : 'warning') }}">{{ ucfirst($payment->status) }}</span>
                            @if ($payment->status === 'pending')
                                <form method="POST" action="{{ route('billing.payments.check', $payment) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-light">{{ __('Check') }}</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">{{ __('No payments yet.') }}</p>
                @endforelse
            </x-ui-card>
        </div>

        <div class="col-lg-5">
            <x-ui-card title="{{ __('AI credit') }}">
                <p class="display-6 mb-1">{{ number_format($balance, 0) }} <small class="fs-6 text-muted">{{ __('credits') }}</small></p>
                <p class="text-muted">{{ __('AI is prepaid: KES 1 buys :credits credits. At zero, AI requests wait until you top up.', ['credits' => $creditsPerKes]) }}</p>

                @if ($onlinePayment)
                    <form method="POST" action="{{ route('billing.ai-topup') }}">
                        @csrf
                        <x-ui-form-input name="amount" label="{{ __('Amount (KES, minimum :min)', ['min' => $minTopUp]) }}" type="number" value="{{ old('amount', $minTopUp) }}" required />
                        <x-ui-form-input name="phone" label="{{ __('M-Pesa phone') }}" required />
                        <x-ui-button variant="primary" type="submit">{{ __('Top up') }}</x-ui-button>
                    </form>
                @else
                    <p class="text-muted mb-0">{{ __('Minimum top-up KES :min. Pay the platform by M-Pesa and we record it.', ['min' => $minTopUp]) }}</p>
                @endif

                @if ($recentUsage->isNotEmpty())
                    <hr>
                    <h6>{{ __('Recent AI use') }}</h6>
                    @foreach ($recentUsage as $event)
                        <div class="small d-flex justify-content-between">
                            <span>{{ $event->created_at->toDayDateTimeString() }} · {{ $event->provider }}</span>
                            <span>{{ number_format((float) $event->credits, 1) }}</span>
                        </div>
                    @endforeach
                @endif
            </x-ui-card>
        </div>
    </div>
@endsection
