@extends('layouts.app')

@section('title', 'Edit Campaign')

@section('content')
    @include('campaigns._form', [
        'campaign' => $campaign,
        'action' => route('campaigns.update', $campaign),
        'method' => 'PUT',
        'shops' => $shops,
        'types' => $types,
        'channels' => $channels,
        'statuses' => $statuses,
        'products' => $products,
    ])
@endsection
