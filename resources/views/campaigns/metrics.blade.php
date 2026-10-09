@extends('layouts.app')

@section('title', $campaign->name . ' — Metrics')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">{{ $campaign->name }} — Metrics</h4>
        </div>
        <div class="card-body">
            <dl class="row">
                <dt class="col-sm-4">Impressions</dt>
                <dd class="col-sm-8">{{ number_format((int) $campaign->impressions) }}</dd>
                <dt class="col-sm-4">Clicks</dt>
                <dd class="col-sm-8">{{ number_format((int) $campaign->clicks) }}</dd>
                <dt class="col-sm-4">Conversions</dt>
                <dd class="col-sm-8">{{ number_format((int) $campaign->conversions) }}</dd>
                <dt class="col-sm-4">CTR</dt>
                <dd class="col-sm-8">{{ number_format($campaign->ctr ?? 0, 2) }}%</dd>
                <dt class="col-sm-4">Spent</dt>
                <dd class="col-sm-8">{{ number_format((float) $campaign->spent, 2) }}</dd>
                <dt class="col-sm-4">Actual Revenue</dt>
                <dd class="col-sm-8">{{ number_format((float) $campaign->actual_revenue, 2) }}</dd>
                <dt class="col-sm-4">ROI</dt>
                <dd class="col-sm-8">{{ number_format($campaign->roi ?? 0, 2) }}%</dd>
                <dt class="col-sm-4">Posts</dt>
                <dd class="col-sm-8">{{ $campaign->posts->count() }}</dd>
            </dl>
        </div>
    </div>
@endsection
