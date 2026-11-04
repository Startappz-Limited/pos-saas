@extends('layouts.app')

@section('title', __('Profit report'))

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">{{ __('Profit report') }}</h4>
            <p class="text-muted mb-0">{{ __('Completed sales, with their cost and profit. Profit is net of VAT. A sale marked "Cost incomplete" has units from a batch with no cost: its figures are estimates until the purchase cost is entered and the sale re-costed.') }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('sales.profitReport') }}" class="row g-2 align-items-end mb-3">
        <div class="col-sm-4 col-md-3">
            <label for="start_date" class="form-label">{{ __('From') }}</label>
            <input type="date" name="start_date" id="start_date" class="form-control" value="{{ \Illuminate\Support\Carbon::parse($startDate)->toDateString() }}">
        </div>
        <div class="col-sm-4 col-md-3">
            <label for="end_date" class="form-label">{{ __('To') }}</label>
            <input type="date" name="end_date" id="end_date" class="form-control" value="{{ \Illuminate\Support\Carbon::parse($endDate)->toDateString() }}">
        </div>
        <div class="col-sm-4 col-md-2">
            <button type="submit" class="btn btn-secondary w-100">{{ __('Show') }}</button>
        </div>
    </form>

    <div class="row g-3 mb-3">
        @foreach ([__('Sales') => $statistics['total_sales'] ?? 0, __('Cost') => $statistics['total_cost'] ?? 0, __('Profit') => $statistics['total_profit'] ?? 0] as $label => $value)
            <div class="col-sm-4">
                <div class="card mb-0"><div class="card-body">
                    <p class="text-muted mb-1">{{ $label }}</p>
                    <h5 class="mb-0">{{ format_currency($value) }}</h5>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Sale') }}</th>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Shop') }}</th>
                            <th class="text-end">{{ __('Total') }}</th>
                            <th class="text-end">{{ __('Cost') }}</th>
                            <th class="text-end">{{ __('Profit') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($sales as $sale)
                            <tr>
                                <td>
                                    <a href="{{ route('sales.show', $sale) }}">{{ $sale->invoice_number }}</a>
                                    @if ($sale->cost_incomplete)
                                        @include('sales.partials.cost-incomplete')
                                    @endif
                                </td>
                                <td>{{ $sale->completed_at?->format('M d, Y') }}</td>
                                <td>{{ $sale->shop?->name }}</td>
                                <td class="text-end">{{ format_currency($sale->total_amount) }}</td>
                                <td class="text-end">{{ format_currency($sale->total_cost) }}</td>
                                <td class="text-end">{{ format_currency($sale->total_profit) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No completed sales in this period.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
