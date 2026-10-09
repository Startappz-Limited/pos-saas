@extends('layouts.app')

@section('title', 'Edit Social Account')

@section('content')
    @include('social-accounts._form', [
        'account' => $account,
        'action' => route('social-accounts.update', $account),
        'method' => 'PUT',
        'shops' => $shops,
        'platforms' => $platforms,
    ])
@endsection
