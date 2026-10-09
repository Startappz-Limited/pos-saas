@extends('layouts.app')

@section('title', __('Abandoned Carts'))

@section('content')

    <!-- Statistics Cards -->
    <div class="row">
        @foreach ([
            ['key' => 'abandoned', 'label' => __('Abandoned'), 'icon' => 'solar:cart-cross-bold-duotone', 'color' => 'warning'],
            ['key' => 'contacted', 'label' => __('Contacted'), 'icon' => 'solar:chat-round-dots-bold-duotone', 'color' => 'info'],
            ['key' => 'recovered', 'label' => __('Recovered online'), 'icon' => 'solar:check-circle-bold-duotone', 'color' => 'success'],
            ['key' => 'converted', 'label' => __('Converted to sale'), 'icon' => 'solar:cart-check-bold-duotone', 'color' => 'primary'],
        ] as $card)
            <div class="col-md-6 col-xl-3">
                <div class="card">
                    <div class="card-body overflow-hidden position-relative">
                        <iconify-icon icon="{{ $card['icon'] }}" class="fs-36 text-{{ $card['color'] }}"></iconify-icon>
                        <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics[$card['key']] ?? 0 }}</h3>
                        <p class="text-muted">{{ $card['label'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header border-bottom-dashed">
            <div class="row g-4 align-items-center">
                <div class="col-sm">
                    <h5 class="card-title mb-0">{{ __('Abandoned Carts') }}</h5>
                    <p class="text-muted small mb-0">
                        {{ __('Website carts left at checkout, sent by the Abandoned Cart Recovery plugin. Follow up, then sell them here.') }}
                    </p>
                </div>
                <div class="col-sm-auto">
                    <span class="badge bg-light text-dark">{{ __('Total') }}: {{ $statistics['total'] ?? 0 }}</span>
                </div>
            </div>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('abandoned-carts.index') }}" class="mb-4">
                <div class="row g-3">
                    <div class="col-xxl-3 col-sm-6">
                        <div class="search-box">
                            <input type="text" name="search" class="form-control search"
                                placeholder="{{ __('Search name, email, phone or cart #...') }}" value="{{ request('search') }}">
                            <iconify-icon icon="solar:magnifer-linear" class="search-icon"></iconify-icon>
                        </div>
                    </div>
                    <div class="col-xxl-2 col-sm-6">
                        <select class="form-select" name="shop_id">
                            <option value="">{{ __('All Shops') }}</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}" @selected(request('shop_id') == $shop->id)>{{ $shop->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xxl-2 col-sm-4">
                        <select class="form-select" name="status">
                            <option value="">{{ __('All Statuses') }}</option>
                            @foreach (\App\Enums\AbandonedCartStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xxl-1 col-sm-4">
                        <input type="date" name="from" class="form-control" value="{{ request('from') }}" title="{{ __('Abandoned from') }}">
                    </div>
                    <div class="col-xxl-1 col-sm-4">
                        <input type="date" name="to" class="form-control" value="{{ request('to') }}" title="{{ __('Abandoned to') }}">
                    </div>
                    <div class="col-xxl-1 col-sm-6">
                        <button type="submit" class="btn btn-primary w-100" title="{{ __('Filter') }}">
                            <iconify-icon icon="solar:filter-bold-duotone" class="align-middle"></iconify-icon>
                        </button>
                    </div>
                    <div class="col-xxl-2 col-sm-6">
                        <a href="{{ route('abandoned-carts.index') }}" class="btn btn-soft-secondary w-100">
                            <iconify-icon icon="solar:refresh-bold-duotone" class="align-middle me-1"></iconify-icon>
                            {{ __('Reset') }}
                        </a>
                    </div>
                </div>
            </form>

            @if ($carts->count() > 0)
                <div class="table-responsive table-card">
                    <table class="table table-nowrap table-striped-columns align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">{{ __('Cart') }}</th>
                                <th scope="col">{{ __('Customer') }}</th>
                                <th scope="col">{{ __('Items') }}</th>
                                <th scope="col">{{ __('Total') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col">{{ __('Reminders') }}</th>
                                <th scope="col">{{ __('Assigned') }}</th>
                                <th scope="col">{{ __('Abandoned') }}</th>
                                <th scope="col">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($carts as $cart)
                                <tr>
                                    <td>
                                        <a href="{{ route('abandoned-carts.show', $cart) }}" class="fw-semibold text-primary">
                                            #{{ $cart->platform_cart_id }}
                                        </a>
                                        <small class="text-muted d-block">{{ $cart->shop->name ?? '' }}</small>
                                    </td>
                                    <td>
                                        {{ $cart->customer_name ?? __('Unknown') }}
                                        @if ($cart->customer_phone)
                                            <small class="text-muted d-block">{{ $cart->customer_phone }}</small>
                                        @elseif ($cart->customer_email)
                                            <small class="text-muted d-block">{{ $cart->customer_email }}</small>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-light text-dark">{{ $cart->items_count }} {{ __('items') }}</span></td>
                                    <td><span class="fw-semibold">{{ $cart->currency }} {{ number_format((float) $cart->total, 2) }}</span></td>
                                    <td>
                                        <span class="badge bg-{{ $cart->status->color() }}-subtle text-{{ $cart->status->color() }}">
                                            {{ $cart->status->label() }}
                                        </span>
                                        @if ($cart->opted_out_at)
                                            <span class="badge bg-dark-subtle text-dark">{{ __('Opted out') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $cart->reminders_sent }}</td>
                                    <td>{{ $cart->assignee->name ?? '—' }}</td>
                                    <td>{{ $cart->abandoned_at?->format('M d, Y H:i') ?? $cart->created_at->format('M d, Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('abandoned-carts.show', $cart) }}" class="btn btn-sm btn-soft-info" title="{{ __('View') }}">
                                            <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                        </a>
                                        @if ($cart->sale)
                                            <a href="{{ route('sales.show', $cart->sale) }}" class="btn btn-sm btn-soft-primary" title="{{ __('Sale') }}">
                                                <iconify-icon icon="solar:bill-list-linear"></iconify-icon>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $carts->links() }}
                </div>
            @else
                <div class="noresult">
                    <div class="text-center">
                        <iconify-icon icon="solar:cart-large-minimalistic-bold-duotone" class="text-primary"
                            style="font-size: 5rem; opacity: 0.5;"></iconify-icon>
                        <h5 class="mt-3">{{ __('No Abandoned Carts Found') }}</h5>
                        <p class="text-muted mb-0">
                            {{ array_filter(request()->only(['search', 'status', 'from', 'to', 'shop_id'])) ? __('Try adjusting your search or filters.') : __('Carts abandoned on your website will appear here once the Abandoned Cart Recovery plugin sends them.') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>

@endsection
