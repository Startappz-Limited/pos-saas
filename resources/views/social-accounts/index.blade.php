@extends('layouts.app')

@section('title', 'Social Accounts')

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Social Accounts</h4>
            @can('social-accounts.connect')
                <a href="{{ route('social-accounts.create') }}" class="btn btn-primary btn-sm">
                    <iconify-icon icon="solar:link-bold-duotone" class="align-middle me-1"></iconify-icon>
                    Connect Account
                </a>
            @endcan
        </div>
        <div class="card-body">
            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-3">
                    <select name="platform" class="form-select">
                        <option value="">All Platforms</option>
                        @foreach ($platforms as $p)
                            <option value="{{ $p->value }}" @selected(request('platform') === $p->value)>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="shop_id" class="form-select">
                        <option value="">All Shops</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->id }}" @selected((string) request('shop_id') === (string) $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2"><button class="btn btn-secondary">Filter</button></div>
            </form>

            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Platform</th>
                            <th>Account</th>
                            <th>Shop</th>
                            <th>Status</th>
                            <th>Token Expires</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $account)
                            <tr>
                                <td>
                                    <span class="badge bg-{{ $account->platform?->color() ?? 'secondary' }}">
                                        <iconify-icon icon="{{ $account->platform?->icon() }}"></iconify-icon>
                                        {{ $account->platform?->label() }}
                                    </span>
                                </td>
                                <td>
                                    {{ $account->account_name }}
                                    @if ($account->username)
                                        <div class="small text-muted">@ {{ $account->username }}</div>
                                    @endif
                                </td>
                                <td>{{ $account->shop?->name }}</td>
                                <td>
                                    @if ($account->is_active && $account->hasValidToken())
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </td>
                                <td>{{ optional($account->token_expires_at)->format('Y-m-d') ?? '—' }}</td>
                                <td class="text-end">
                                    @can('update', $account)
                                        <a href="{{ route('social-accounts.edit', $account) }}"
                                            class="btn btn-sm btn-light">Edit</a>
                                    @endcan
                                    @can('delete', $account)
                                        <form method="POST" action="{{ route('social-accounts.destroy', $account) }}"
                                            class="d-inline" onsubmit="return confirm('Disconnect this account?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger">Disconnect</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No accounts connected.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $accounts->links() }}</div>
        </div>
    </div>
@endsection
