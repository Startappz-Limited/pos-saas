@extends('errors.layout')

@section('code', '422')
@section('title', __('Couldn\'t process that'))
@section('message'){{ $exception->getMessage() !== '' ? $exception->getMessage() : __('Some of what you sent wasn\'t accepted. Go back, check the form and try again.') }}@endsection
