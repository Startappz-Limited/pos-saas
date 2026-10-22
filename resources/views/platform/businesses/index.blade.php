@extends('layouts.platform')

@section('title', __('Businesses'))

@section('breadcrumb')
    <li class="breadcrumb-item active">{{ __('Businesses') }}</li>
@endsection

@section('content')
    <x-ui-card :title="__('Every business on the platform')">
        <form method="GET" action="{{ route('platform.businesses.index') }}" class="row g-2 mb-3">
            <div class="col-md-5">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm"
                    placeholder="{{ __('Name or uuid') }}" aria-label="{{ __('Search') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                    <option value="">{{ __('Every status') }}</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ \Illuminate\Support\Str::headline($status) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Filter') }}</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Business') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Plan') }}</th>
                        <th class="text-end">{{ __('Shops') }}</th>
                        <th class="text-end">{{ __('Registers') }}</th>
                        <th class="text-end">{{ __('Members') }}</th>
                        <th class="text-end">{{ __('Sales (30 days)') }}</th>
                        <th class="text-end">{{ __('AI balance') }}</th>
                        <th>{{ __('Ends') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($businesses as $business)
                        <tr>
                            <td><a href="{{ route('platform.businesses.show', $business) }}" class="fw-medium">{{ $business->name }}</a></td>
                            <td>@include('platform.businesses.partials.status', ['status' => $business->status])</td>
                            <td>{{ $business->plan_name ?? '—' }}
                                @if ($business->subscription_status)
                                    <span class="text-muted small">({{ \Illuminate\Support\Str::headline($business->subscription_status) }})</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $business->shops_count }}</td>
                            <td class="text-end">{{ $business->registers_count }}</td>
                            <td class="text-end">{{ $business->members_count }}</td>
                            <td class="text-end">{{ $business->sales_30_days }}</td>
                            <td class="text-end">{{ $business->ai_balance !== null ? number_format((float) $business->ai_balance, 2) : '—' }}</td>
                            <td>{{ $business->subscription_ends_at ? \Illuminate\Support\Carbon::parse($business->subscription_ends_at)->format('d M Y') : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">{{ __('No business matches.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">{{ $businesses->links() }}</div>
    </x-ui-card>
@endsection
