@extends('layouts.app')

@section('title', 'Active Campaigns')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Active Campaigns</h4>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Shop</th>
                            <th>Channel</th>
                            <th class="text-end">Budget</th>
                            <th class="text-end">Spent</th>
                            <th>Ends</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaigns as $campaign)
                            <tr>
                                <td><code>{{ $campaign->code }}</code></td>
                                <td><a href="{{ route('campaigns.show', $campaign) }}">{{ $campaign->name }}</a></td>
                                <td>{{ $campaign->shop?->name }}</td>
                                <td>{{ $campaign->channel?->label() }}</td>
                                <td class="text-end">{{ number_format((float) $campaign->budget, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $campaign->spent, 2) }}</td>
                                <td>{{ optional($campaign->end_date)->format('Y-m-d') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No active campaigns.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $campaigns->links() }}</div>
        </div>
    </div>
@endsection
