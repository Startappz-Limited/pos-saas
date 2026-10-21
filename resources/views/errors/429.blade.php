@extends('errors.layout')

@section('code', '429')
@section('title', __('Too many attempts'))
@section('message'){{ __('You\'ve tried this too many times. Wait a minute, then try again.') }}@endsection
