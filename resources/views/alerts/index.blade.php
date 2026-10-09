@extends('layouts.app')

@section('title', __('Alerts'))

@section('content')
    <div>
        <!-- Statistics -->
        <div class="row">
            @foreach ([
                ['label' => __('Total Alerts'), 'value' => $statistics['total'], 'tone' => 'primary', 'icon' => 'solar:bell-bing-bold-duotone'],
                ['label' => __('Unread'), 'value' => $statistics['unread'], 'tone' => 'info', 'icon' => 'solar:letter-unread-bold-duotone'],
                ['label' => __('Unresolved'), 'value' => $statistics['unresolved'], 'tone' => 'warning', 'icon' => 'solar:hourglass-bold-duotone'],
                ['label' => __('Critical Open'), 'value' => $statistics['critical'], 'tone' => 'danger', 'icon' => 'solar:danger-triangle-bold-duotone'],
            ] as $tile)
                <div class="col-xl-3 col-md-6">
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
                <h5 class="card-title mb-0">
                    {{ request()->routeIs('alerts.unresolved') ? __('Unresolved Alerts') : __('Alerts') }}
                </h5>

                <div class="d-flex gap-2">
                    <form method="POST" action="{{ route('alerts.markAllRead') }}">
                        @csrf
                        @honeypot
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <iconify-icon icon="solar:check-read-outline" aria-hidden="true"></iconify-icon>
                            {{ __('Mark all read') }}
                        </button>
                    </form>

                    @can('create', App\Models\Alert::class)
                        {{-- There is no alerts.create route (the resource exposes index/show/store
                             only), so the form lives in a modal rather than its own page. --}}
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal"
                            data-bs-target="#createAlertModal">
                            {{ __('New Alert') }}
                        </button>
                    @endcan
                </div>
            </div>

            <!-- Filters -->
            <div class="card-body border-bottom">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="filter-shop" class="form-label">{{ __('Shop') }}</label>
                        <select id="filter-shop" name="shop_id" class="form-select">
                            <option value="">{{ __('All shops') }}</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}" @selected(request('shop_id') == $shop->id)>
                                    {{ $shop->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="filter-category" class="form-label">{{ __('Category') }}</label>
                        <select id="filter-category" name="category" class="form-select">
                            <option value="">{{ __('All categories') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->value }}" @selected(request('category') === $category->value)>
                                    {{ $category->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="filter-severity" class="form-label">{{ __('Severity') }}</label>
                        <select id="filter-severity" name="severity" class="form-select">
                            <option value="">{{ __('All severities') }}</option>
                            @foreach ($severities as $severity)
                                <option value="{{ $severity->value }}" @selected(request('severity') === $severity->value)>
                                    {{ $severity->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3 d-flex gap-2">
                        <div class="form-check align-self-center">
                            <input class="form-check-input" type="checkbox" id="filter-unread" name="unread_only"
                                value="1" @checked(request()->boolean('unread_only'))>
                            <label class="form-check-label" for="filter-unread">{{ __('Unread only') }}</label>
                        </div>
                        <button type="submit" class="btn btn-secondary ms-auto">{{ __('Filter') }}</button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">{{ __('Alert') }}</th>
                                <th scope="col">{{ __('Category') }}</th>
                                <th scope="col">{{ __('Severity') }}</th>
                                <th scope="col">{{ __('Shop') }}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col">{{ __('Raised') }}</th>
                                <th scope="col" class="text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($alerts as $alert)
                                <tr class="{{ $alert->is_read ? '' : 'fw-semibold' }}">
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <iconify-icon icon="{{ $alert->type->icon() }}"
                                                aria-hidden="true"></iconify-icon>
                                            <div>
                                                <a href="{{ route('alerts.show', $alert) }}">{{ $alert->title }}</a>
                                                <div class="text-muted small">{{ Str::limit($alert->message, 70) }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $alert->category->label() }}</td>
                                    <td>
                                        <span class="badge bg-{{ $alert->severity->color() }}">
                                            {{ $alert->severity->label() }}
                                        </span>
                                    </td>
                                    <td>{{ $alert->shop?->name ?? __('System-wide') }}</td>
                                    <td>
                                        @if ($alert->is_resolved)
                                            <span class="badge bg-success">{{ __('Resolved') }}</span>
                                        @else
                                            <span class="badge bg-warning">{{ __('Open') }}</span>
                                        @endif

                                        @unless ($alert->is_read)
                                            <span class="badge bg-info">{{ __('Unread') }}</span>
                                        @endunless
                                    </td>
                                    <td>{{ $alert->created_at?->diffForHumans() }}</td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            @can('update', $alert)
                                                @unless ($alert->is_read)
                                                    <form method="POST" action="{{ route('alerts.markRead', $alert) }}">
                                                        @csrf
                                                        @honeypot
                                                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                            aria-label="{{ __('Mark as read') }}">
                                                            <iconify-icon icon="solar:eye-outline"
                                                                aria-hidden="true"></iconify-icon>
                                                        </button>
                                                    </form>
                                                @endunless
                                            @endcan

                                            <a href="{{ route('alerts.show', $alert) }}"
                                                class="btn btn-sm btn-outline-primary">{{ __('View') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        {{ __('No alerts found.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($alerts->hasPages())
                <div class="card-footer">
                    {{ $alerts->links() }}
                </div>
            @endif
        </div>
    </div>

    @can('create', App\Models\Alert::class)
        <div class="modal fade" id="createAlertModal" tabindex="-1" aria-labelledby="createAlertModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form method="POST" action="{{ route('alerts.store') }}" class="modal-content">
                    @csrf
                    @honeypot

                    <div class="modal-header">
                        <h5 class="modal-title" id="createAlertModalLabel">{{ __('New Alert') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="{{ __('Close') }}"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="alert-title" class="form-label">{{ __('Title') }}</label>
                                <input type="text" class="form-control @error('title') is-invalid @enderror"
                                    id="alert-title" name="title" value="{{ old('title') }}" required maxlength="255">
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-12">
                                <label for="alert-message" class="form-label">{{ __('Message') }}</label>
                                <textarea class="form-control @error('message') is-invalid @enderror" id="alert-message" name="message" rows="3"
                                    required maxlength="2000">{{ old('message') }}</textarea>
                                @error('message')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="alert-type" class="form-label">{{ __('Type') }}</label>
                                <select class="form-select @error('type') is-invalid @enderror" id="alert-type"
                                    name="type" required>
                                    @foreach ($types as $type)
                                        <option value="{{ $type->value }}" @selected(old('type') === $type->value)>
                                            {{ $type->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('type')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="alert-category" class="form-label">{{ __('Category') }}</label>
                                <select class="form-select @error('category') is-invalid @enderror" id="alert-category"
                                    name="category" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->value }}" @selected(old('category') === $category->value)>
                                            {{ $category->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="alert-severity" class="form-label">{{ __('Severity') }}</label>
                                <select class="form-select @error('severity') is-invalid @enderror" id="alert-severity"
                                    name="severity" required>
                                    @foreach ($severities as $severity)
                                        <option value="{{ $severity->value }}" @selected(old('severity') === $severity->value)>
                                            {{ $severity->label() }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('severity')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="alert-shop" class="form-label">{{ __('Shop') }}</label>
                                <select class="form-select @error('shop_id') is-invalid @enderror" id="alert-shop"
                                    name="shop_id">
                                    <option value="">{{ __('System-wide (no shop)') }}</option>
                                    @foreach ($shops as $shop)
                                        <option value="{{ $shop->id }}" @selected(old('shop_id') == $shop->id)>
                                            {{ $shop->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('shop_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="alert-scheduled" class="form-label">{{ __('Scheduled for') }}</label>
                                <input type="datetime-local"
                                    class="form-control @error('scheduled_at') is-invalid @enderror" id="alert-scheduled"
                                    name="scheduled_at" value="{{ old('scheduled_at') }}">
                                @error('scheduled_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="alert-expires" class="form-label">{{ __('Expires') }}</label>
                                <input type="datetime-local"
                                    class="form-control @error('expires_at') is-invalid @enderror" id="alert-expires"
                                    name="expires_at" value="{{ old('expires_at') }}">
                                @error('expires_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('Create alert') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endcan
@endsection

@push('scripts')
    {{-- Re-open the modal when validation failed so the user does not lose input. --}}
    @if ($errors->any() && old('title') !== null)
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                new bootstrap.Modal(document.querySelector('#createAlertModal')).show();
            });
        </script>
    @endif
@endpush
