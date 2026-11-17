@extends('layouts.app')

@section('title', __('Expiring stock'))

@section('content')
    <div>
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1">{{ __('Expiring stock') }}</h4>
                <p class="text-muted mb-0">{{ __('Batches that have expired, and those expiring soon, at one shop. An expired batch is never sold.') }}</p>
            </div>
            @if ($shop && $expired->isNotEmpty())
                @can('create', App\Models\StockAdjustment::class)
                    <form method="POST" action="{{ route('stock.expiring.write-off') }}"
                        onsubmit="return confirm(@js(__('File an adjustment writing off all expired stock at :shop? Someone else has to approve it before stock changes.', ['shop' => $shop->name])));">
                        @csrf
                        @honeypot
                        <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                        <button type="submit" class="btn btn-danger">
                            <iconify-icon icon="solar:trash-bin-trash-bold-duotone"></iconify-icon>
                            {{ __('Write off expired') }}
                        </button>
                    </form>
                @endcan
            @endif
        </div>

        <form method="GET" action="{{ route('stock.expiring') }}" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label" for="shop_id">{{ __('Shop') }}</label>
                <select class="form-select" name="shop_id" id="shop_id" onchange="this.form.submit()">
                    @foreach ($shops as $option)
                        <option value="{{ $option->id }}" @selected($shop && $shop->id === $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="days">{{ __('Expiring within (days)') }}</label>
                <input class="form-control" type="number" min="1" max="365" name="days" id="days" value="{{ $days }}">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">{{ __('Show') }}</button>
            </div>
        </form>

        @if ($fractionsLeft->isNotEmpty())
            <div class="alert alert-warning">
                {{ __(':count expired batch(es) hold a fraction of a unit that a write-off cannot take yet. It stays on the shelf until adjustments can name any unit.', ['count' => $fractionsLeft->count()]) }}
            </div>
        @endif

        @foreach ([['title' => __('Expired'), 'rows' => $expired, 'badge' => 'danger'], ['title' => __('Expiring within :days days', ['days' => $days]), 'rows' => $expiring, 'badge' => 'warning']] as $section)
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ $section['title'] }} <span class="badge bg-{{ $section['badge'] }}-subtle text-{{ $section['badge'] }}">{{ $section['rows']->count() }}</span></h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Product') }}</th>
                                <th>{{ __('Batch') }}</th>
                                <th>{{ __('Expiry') }}</th>
                                <th class="text-end">{{ __('Quantity') }}</th>
                                @if ($showCosts)
                                    <th class="text-end">{{ __('Value') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($section['rows'] as $layer)
                                <tr>
                                    <td>
                                        {{ $layer->product?->name }}
                                        @if ($layer->variation && ! $layer->variation->is_default)
                                            <span class="text-muted">— {{ $layer->variation->name }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $layer->batch_number ?? '—' }}</td>
                                    <td>{{ $layer->expiry_date->toDateString() }}</td>
                                    <td class="text-end">{{ \App\Support\Stock\UnitBreakdown::describe($layer->variation, $layer->remaining_quantity) }}</td>
                                    @if ($showCosts)
                                        <td class="text-end">
                                            @if ($layer->unit_cost === null)
                                                <span class="text-muted">{{ __('Cost unknown') }}</span>
                                            @else
                                                {{ number_format((float) bcmul((string) $layer->remaining_quantity, (string) $layer->unit_cost, 4), 2) }}
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $showCosts ? 5 : 4 }}" class="text-center text-muted py-4">{{ __('Nothing here.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
@endsection
