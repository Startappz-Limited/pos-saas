@extends('layouts.app')

@section('title', __('VAT Summary'))

@section('content')
    @php
        $totals = $summary['totals'];
        $taxLabel = config('tax.label', 'VAT');
    @endphp

    <div class="card mb-4">
        <div class="card-header border-bottom-dashed d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">{{ $taxLabel }} {{ __('Summary') }}</h5>
            @can('reports.export')
                <a href="{{ route('reports.vat.export', request()->query()) }}" class="btn btn-sm btn-outline-primary">
                    <iconify-icon icon="solar:download-minimalistic-bold-duotone" class="align-middle me-1"></iconify-icon>
                    {{ __('Export CSV') }}
                </a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reports.vat') }}" class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label" for="start_date">{{ __('Period start') }}</label>
                    <input type="date" class="form-control" id="start_date" name="start_date"
                        value="{{ $from->toDateString() }}">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label" for="end_date">{{ __('Period end') }}</label>
                    <input type="date" class="form-control" id="end_date" name="end_date"
                        value="{{ $to->toDateString() }}">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="shop_id">{{ __('Shop') }}</label>
                    <select class="form-select" id="shop_id" name="shop_id">
                        <option value="">{{ __('All shops') }}</option>
                        @foreach ($shops as $option)
                            <option value="{{ $option->id }}" @selected($shop?->id === $option->id)>
                                {{ $option->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">{{ __('Apply') }}</button>
                </div>
            </form>

            <p class="text-muted small mt-3 mb-0">
                {{ __('Kenyan VAT returns are filed monthly, by the 20th of the following month. The default period is the month just ended.') }}
                @if ($shop?->tax_pin)
                    <br><strong>{{ __('PIN') }}:</strong> {{ $shop->tax_pin }}
                @endif
            </p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
        ['label' => __('Taxable supplies'), 'value' => $totals['taxable_supplies'], 'tone' => 'primary'],
        ['label' => __('Exempt supplies'), 'value' => $totals['exempt_supplies'], 'tone' => 'secondary'],
        ['label' => __('Output :label', ['label' => $taxLabel]), 'value' => $totals['output_tax'], 'tone' => 'info'],
        ['label' => __('Net :label payable', ['label' => $taxLabel]), 'value' => $totals['net_output_tax'], 'tone' => 'success'],
    ] as $tile)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <p class="text-muted mb-1">{{ $tile['label'] }}</p>
                        <h4 class="mb-0 text-{{ $tile['tone'] }}">{{ format_currency($tile['value']) }}</h4>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card mb-4">
        <div class="card-header border-bottom-dashed">
            <h5 class="card-title mb-0">{{ __('Supplies by tax class') }}</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">{{ __('Tax class') }}</th>
                            <th scope="col">{{ __('KRA code') }}</th>
                            <th scope="col" class="text-end">{{ __('Rate') }}</th>
                            <th scope="col" class="text-end">{{ __('Invoices') }}</th>
                            <th scope="col" class="text-end">{{ __('Taxable amount') }}</th>
                            <th scope="col" class="text-end">{{ __('Output :label', ['label' => $taxLabel]) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary['bands'] as $band)
                            <tr>
                                <td>
                                    <span class="badge {{ $band['class']?->badgeClass() ?? 'bg-light text-muted' }}">
                                        {{ $band['label'] }}
                                    </span>
                                </td>
                                <td>{{ $band['kra_code'] }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($band['rate'], 2), '0'), '.') }}%</td>
                                <td class="text-end">{{ number_format($band['invoices']) }}</td>
                                <td class="text-end">{{ format_currency($band['taxable_amount']) }}</td>
                                <td class="text-end fw-semibold">{{ format_currency($band['tax_amount']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    {{ __('No VAT-classified sales in this period.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($summary['credit_notes'] !== [])
        <div class="card mb-4">
            <div class="card-header border-bottom-dashed">
                <h5 class="card-title mb-0">{{ __('Credit notes (customer returns)') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">{{ __('Tax class') }}</th>
                                <th scope="col" class="text-end">{{ __('Rate') }}</th>
                                <th scope="col" class="text-end">{{ __('Credited amount') }}</th>
                                <th scope="col" class="text-end">{{ __(':label credited', ['label' => $taxLabel]) }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['credit_notes'] as $band)
                                <tr>
                                    <td>{{ $band['label'] }}</td>
                                    <td class="text-end">{{ rtrim(rtrim(number_format($band['rate'], 2), '0'), '.') }}%
                                    </td>
                                    <td class="text-end">{{ format_currency($band['credited_amount']) }}</td>
                                    <td class="text-end fw-semibold">-{{ format_currency($band['tax_amount']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if ($summary['unclassified']['invoices'] > 0)
        <div class="alert alert-warning">
            <iconify-icon icon="solar:danger-triangle-bold" class="align-middle me-1"></iconify-icon>
            {{ __(':count sale(s) totalling :amount carry no tax class and are excluded from this summary. They were recorded before VAT was enabled for the shop.', [
                'count' => number_format($summary['unclassified']['invoices']),
                'amount' => format_currency($summary['unclassified']['amount']),
            ]) }}
        </div>
    @endif

    <div class="alert alert-info">
        <iconify-icon icon="solar:info-circle-bold" class="align-middle me-1"></iconify-icon>
        <small>
            {{ __('This covers output VAT on sales only. Input VAT on purchases is not tracked here, so it must be added before the return is filed. Treat these figures as a reconciliation aid, not as the return itself.') }}
        </small>
    </div>
@endsection
