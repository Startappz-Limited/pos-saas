@extends('layouts.app')

@section('title', __('Stock valuation'))

@section('content')
    <div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1">{{ __('Stock valuation') }}</h4>
                <p class="text-muted mb-0">{{ __('What each shop\'s stock was worth on one day, from its snapshots: cost at FIFO, and at the shop\'s selling price. Quantities are in base units.') }}</p>
            </div>
            <a href="{{ route('inventory-snapshots.index') }}" class="btn btn-sm btn-light">{{ __('All snapshots') }}</a>
        </div>

        <form method="GET" action="{{ route('inventory-snapshots.valuationReport') }}" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label" for="date">{{ __('Day') }}</label>
                <input class="form-control" type="date" name="date" id="date" value="{{ $day }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">{{ __('Show') }}</button>
            </div>
        </form>

        @if ($day === null)
            <div class="alert alert-info">{{ __('No snapshot has been taken yet.') }}</div>
        @elseif ($rows->isEmpty())
            <div class="alert alert-info">{{ __('No snapshot was taken on :day for a shop you can see.', ['day' => $day]) }}</div>
        @else
            <div class="card">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Shop') }}</th>
                                <th class="text-end">{{ __('Variations') }}</th>
                                <th class="text-end">{{ __('Value at cost (FIFO)') }}</th>
                                <th class="text-end">{{ __('Value at selling price') }}</th>
                                <th class="text-end">{{ __('Held without a known cost') }}</th>
                                <th class="text-end">{{ __('Below zero (no value)') }}</th>
                                <th>{{ __('Source') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row['shop']?->name ?? '—' }}</td>
                                    <td class="text-end">{{ $row['variations'] }}</td>
                                    <td class="text-end">{{ number_format($row['total_value'], 2) }}</td>
                                    <td class="text-end">{{ number_format($row['retail_value'], 2) }}</td>
                                    <td class="text-end">{{ \App\Models\Product::quantityLabel($row['unknown_cost_quantity']) }}</td>
                                    <td class="text-end">{{ \App\Models\Product::quantityLabel($row['shortfall_quantity']) }}</td>
                                    <td>{{ $row['rebuilt'] ? __('Rebuilt or late') : __('On time') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
