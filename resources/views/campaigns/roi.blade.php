@extends('layouts.app')

@section('title', $campaign->name . ' — ROI')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">{{ $campaign->name }} — ROI</h4>
        </div>
        <div class="card-body">
            <p class="display-6 mb-0">{{ number_format($campaign->roi ?? 0, 2) }}%</p>
            <p class="text-muted">Spent {{ number_format((float) $campaign->spent, 2) }} {{ $campaign->currency }} —
                Revenue {{ number_format((float) $campaign->actual_revenue, 2) }} {{ $campaign->currency }}</p>
        </div>
    </div>
@endsection
