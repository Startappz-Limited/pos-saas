@extends('errors.layout')

@section('code', '402')
@section('title', __('Subscription needed'))
@section('message'){{ $exception->getMessage() !== '' ? $exception->getMessage() : __('This business can\'t make changes right now. Check the billing page to renew or adjust its plan; you can still view and export your data.') }}@endsection
