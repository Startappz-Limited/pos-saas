@extends('layouts.app')

@section('title', __('Payments'))

@section('content')
    <div>
        <div class="row">
            @foreach ([
                ['label' => __('Payments'), 'value' => number_format($statistics['payment_count']), 'tone' => 'primary', 'icon' => 'solar:wallet-money-bold-duotone'],
                ['label' => __('Total Received'), 'value' => format_currency($statistics['total_amount']), 'tone' => 'success', 'icon' => 'solar:hand-money-bold-duotone'],
                ['label' => __('Received Today'), 'value' => format_currency($statistics['today_amount']), 'tone' => 'info', 'icon' => 'solar:calendar-mark-bold-duotone'],
            ] as $tile)
                <div class="col-xl-4 col-md-6">
                    <div class="card card-height-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm flex-shrink-0">
                                    <span
                                        class="avatar-title bg-{{ $tile['tone'] }}-subtle text-{{ $tile['tone'] }} rounded fs-3">
                                        <iconify-icon icon="{{ $tile['icon'] }}" aria-hidden="true"></iconify-icon>
                                    </span>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <p class="text-uppercase fw-medium text-muted mb-1">{{ $tile['label'] }}</p>
                                    <h4 class="mb-0">{{ $tile['value'] }}</h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h5 class="card-title mb-0">{{ __('Payments') }}</h5>

                <div class="d-flex gap-2">
                    <a href="{{ route('payments.dailyReport') }}"
                        class="btn btn-sm btn-outline-secondary">{{ __('Daily report') }}</a>
                    <a href="{{ route('payments.methodsReport') }}"
                        class="btn btn-sm btn-outline-secondary">{{ __('By method') }}</a>
                </div>
            </div>

            <div class="card-body border-bottom">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="filter-shop" class="form-label">{{ __('Shop') }}</label>
                        <select id="filter-shop" name="shop_id" class="form-select">
                            <option value="">{{ __('All shops') }}</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}" @selected($filters['shop_id'] == $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="filter-method" class="form-label">{{ __('Method') }}</label>
                        <select id="filter-method" name="payment_method" class="form-select">
                            <option value="">{{ __('All methods') }}</option>
                            @foreach ($methods as $method)
                                <option value="{{ $method }}" @selected($filters['payment_method'] === $method)>
                                    {{ ucfirst(str_replace('_', ' ', $method)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="filter-search" class="form-label">{{ __('Search') }}</label>
                        <input type="search" id="filter-search" name="search" class="form-control"
                            value="{{ $filters['search'] }}" placeholder="{{ __('Payment no. / reference / invoice') }}">
                    </div>

                    <div class="col-md-2">
                        <button type="submit" class="btn btn-secondary w-100">{{ __('Filter') }}</button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">{{ __('Payment No.') }}</th>
                                <th scope="col">{{ __('Invoice') }}</th>
                                <th scope="col">{{ __('Customer') }}</th>
                                <th scope="col">{{ __('Shop') }}</th>
                                <th scope="col">{{ __('Method') }}</th>
                                <th scope="col" class="text-end">{{ __('Amount') }}</th>
                                <th scope="col">{{ __('Received') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payments as $payment)
                                <tr>
                                    <td>
                                        <a href="{{ route('payments.show', $payment) }}">{{ $payment->payment_number }}</a>
                                    </td>
                                    <td>{{ $payment->sale?->invoice_number ?? '—' }}</td>
                                    <td>{{ $payment->sale?->customer?->name ?? __('Walk-in') }}</td>
                                    <td>{{ $payment->sale?->shop?->name ?? '—' }}</td>
                                    <td><span class="badge bg-secondary">{{ $payment->payment_method_label }}</span></td>
                                    <td class="text-end fw-medium">{{ format_currency($payment->amount) }}</td>
                                    <td>
                                        {{ $payment->paid_at?->format('M d, Y H:i') }}
                                        <div class="text-muted small">{{ $payment->receiver?->name }}</div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">{{ __('No payments found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($payments->hasPages())
                <div class="card-footer">{{ $payments->links() }}</div>
            @endif
        </div>
    </div>
@endsection
