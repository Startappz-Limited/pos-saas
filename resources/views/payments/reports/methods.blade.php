@extends('layouts.app')

@section('title', __('Payments by Method'))

@section('content')
    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h5 class="card-title mb-0">{{ __('Payments by Method') }}</h5>
            <a href="{{ route('payments.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('All payments') }}</a>
        </div>

        <div class="card-body border-bottom">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="report-from" class="form-label">{{ __('From') }}</label>
                    <input type="date" id="report-from" name="from" class="form-control"
                        value="{{ $filters['from']->toDateString() }}">
                </div>

                <div class="col-md-3">
                    <label for="report-to" class="form-label">{{ __('To') }}</label>
                    <input type="date" id="report-to" name="to" class="form-control"
                        value="{{ $filters['to']->toDateString() }}">
                </div>

                <div class="col-md-4">
                    <label for="report-shop" class="form-label">{{ __('Shop') }}</label>
                    <select id="report-shop" name="shop_id" class="form-select">
                        <option value="">{{ __('All shops') }}</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->id }}" @selected($filters['shop_id'] == $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">{{ __('Apply') }}</button>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">{{ __('Method') }}</th>
                            <th scope="col" class="text-end">{{ __('Payments') }}</th>
                            <th scope="col" class="text-end">{{ __('Total') }}</th>
                            <th scope="col" class="text-end">{{ __('Share') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ ucfirst(str_replace('_', ' ', $row->payment_method)) }}</td>
                                <td class="text-end">{{ number_format($row->payment_count) }}</td>
                                <td class="text-end fw-medium">{{ format_currency($row->total_amount) }}</td>
                                <td class="text-end">
                                    {{ $totals['total_amount'] > 0 ? number_format(($row->total_amount / $totals['total_amount']) * 100, 1) . '%' : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    {{ __('No payments in this period.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($rows->isNotEmpty())
                        <tfoot class="table-light fw-semibold">
                            <tr>
                                <td>{{ __('Total') }}</td>
                                <td class="text-end">{{ number_format($totals['payment_count']) }}</td>
                                <td class="text-end">{{ format_currency($totals['total_amount']) }}</td>
                                <td class="text-end">100%</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
@endsection
