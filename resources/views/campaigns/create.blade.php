@extends('layouts.app')

@section('title', 'Create Campaign')

@section('content')
    @include('campaigns._form', [
        'campaign' => null,
        'action' => route('campaigns.store'),
        'method' => 'POST',
        'shops' => $shops,
        'types' => $types,
        'channels' => $channels,
        'statuses' => $statuses,
        'products' => $products,
    ])
@endsection
