@extends('layouts.app')

@section('title', 'Campaigns')

@section('content')
    <div>
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="row mb-3">
            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-primary-subtle text-primary rounded fs-3">
                                <iconify-icon icon="solar:leaf-bold-duotone"></iconify-icon>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-uppercase fw-medium text-muted mb-1">Total</p>
                            <h4 class="mb-0">{{ $statistics['total'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-success-subtle text-success rounded fs-3">
                                <iconify-icon icon="solar:play-circle-bold-duotone"></iconify-icon>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-uppercase fw-medium text-muted mb-1">Active</p>
                            <h4 class="mb-0">{{ $statistics['active'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-info-subtle text-info rounded fs-3">
                                <iconify-icon icon="solar:calendar-bold-duotone"></iconify-icon>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-uppercase fw-medium text-muted mb-1">Scheduled</p>
                            <h4 class="mb-0">{{ $statistics['scheduled'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card card-height-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title bg-secondary-subtle text-secondary rounded fs-3">
                                <iconify-icon icon="solar:check-circle-bold-duotone"></iconify-icon>
                            </span>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="text-uppercase fw-medium text-muted mb-1">Completed</p>
                            <h4 class="mb-0">{{ $statistics['completed'] ?? 0 }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">All Campaigns</h4>
                @can('campaigns.create')
                    <a href="{{ route('campaigns.create') }}" class="btn btn-primary btn-sm">
                        <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                        New Campaign
                    </a>
                @endcan
            </div>
            <div class="card-body">
                <form method="GET" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="Search…"
                            value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            @foreach ($statuses as $s)
                                <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="channel" class="form-select">
                            <option value="">All Channels</option>
                            @foreach ($channels as $c)
                                <option value="{{ $c->value }}" @selected(($filters['channel'] ?? '') === $c->value)>{{ $c->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="shop_id" class="form-select">
                            <option value="">All Shops</option>
                            @foreach ($shops as $shop)
                                <option value="{{ $shop->id }}" @selected((string) ($filters['shop_id'] ?? '') === (string) $shop->id)>{{ $shop->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-secondary">Filter</button>
                        <a href="{{ route('campaigns.index') }}" class="btn btn-light">Reset</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Channel</th>
                                <th>Status</th>
                                <th class="text-end">Budget</th>
                                <th class="text-end">Spent</th>
                                <th>Period</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($campaigns as $campaign)
                                <tr>
                                    <td><code>{{ $campaign->code }}</code></td>
                                    <td>
                                        <a href="{{ route('campaigns.show', $campaign) }}">{{ $campaign->name }}</a>
                                        <div class="small text-muted">{{ $campaign->shop?->name }}</div>
                                    </td>
                                    <td>{{ $campaign->channel?->label() }}</td>
                                    <td><span
                                            class="badge bg-{{ $campaign->status?->color() ?? 'secondary' }}">{{ $campaign->status?->label() }}</span>
                                    </td>
                                    <td class="text-end">{{ number_format((float) $campaign->budget, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $campaign->spent, 2) }}</td>
                                    <td class="small">{{ optional($campaign->start_date)->format('Y-m-d') }} →
                                        {{ optional($campaign->end_date)->format('Y-m-d') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('campaigns.show', $campaign) }}" class="btn btn-sm btn-light">
                                            <iconify-icon icon="solar:eye-bold-duotone"></iconify-icon>
                                        </a>
                                        @can('update', $campaign)
                                            <a href="{{ route('campaigns.edit', $campaign) }}" class="btn btn-sm btn-light">
                                                <iconify-icon icon="solar:pen-bold-duotone"></iconify-icon>
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No campaigns yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">{{ $campaigns->links() }}</div>
            </div>
        </div>
    </div>
@endsection
