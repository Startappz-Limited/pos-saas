@extends('errors.layout')

@section('code', '410')
@section('title', __('No longer available'))
@section('message'){{ __('This link has expired or is no longer available.') }}@endsection
