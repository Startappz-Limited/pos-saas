@extends('layouts.app')

@section('title', __('Void requests'))

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">{{ __('Void requests') }}</h4>
            <p class="text-muted mb-0">{{ __('Sales someone asked to void. The first approval voids the sale; a rejection needs a reason. Each can be approved for :hours hours after it was asked.', ['hours' => \App\Models\SaleVoidRequest::EXPIRES_AFTER_HOURS]) }}</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Sale') }}</th>
                            <th>{{ __('Shop') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            <th>{{ __('Asked by') }}</th>
                            <th>{{ __('Reason') }}</th>
                            <th>{{ __('Asked') }}</th>
                            <th><span class="visually-hidden">{{ __('Actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pending as $voidRequest)
                            <tr>
                                <td><a href="{{ route('sales.show', $voidRequest->sale) }}">{{ $voidRequest->sale?->invoice_number }}</a></td>
                                <td>{{ $voidRequest->shop?->name }}</td>
                                <td class="text-end">{{ format_currency($voidRequest->sale?->total_amount) }}</td>
                                <td>{{ $voidRequest->requester?->name ?? __('a former member') }}</td>
                                <td>{{ $voidRequest->reason }}</td>
                                <td>{{ $voidRequest->requested_at?->diffForHumans() }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('sales.show', $voidRequest->sale) }}" class="btn btn-soft-primary btn-sm">{{ __('Review') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ __('No void requests are waiting.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
