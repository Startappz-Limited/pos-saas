@extends('layouts.app')

@section('title', 'Connect Social Account')

@section('content')
    @include('social-accounts._form', [
        'account' => null,
        'action' => route('social-accounts.store'),
        'method' => 'POST',
        'shops' => $shops,
        'platforms' => $platforms,
    ])
@endsection
