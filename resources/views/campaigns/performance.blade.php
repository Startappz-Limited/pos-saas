@extends('layouts.app')

@section('title', 'Campaign Performance')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Campaign Performance</h4>
        </div>
        <div class="card-body table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Channel</th>
                        <th class="text-end">Spent</th>
                        <th class="text-end">Revenue</th>
                        <th class="text-end">ROI</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $campaign)
                        <tr>
                            <td><a href="{{ route('campaigns.show', $campaign) }}">{{ $campaign->name }}</a></td>
                            <td>{{ $campaign->channel?->label() }}</td>
                            <td class="text-end">{{ number_format((float) $campaign->spent, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $campaign->actual_revenue, 2) }}</td>
                            <td class="text-end">{{ number_format($campaign->roi ?? 0, 2) }}%</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No campaigns.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
