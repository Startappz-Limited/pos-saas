@extends('errors.layout')

@section('code', '409')
@section('title', __('Out of date'))
@section('message'){{ $exception->getMessage() !== '' ? $exception->getMessage() : __('This page is out of date. Reload it and try again.') }}@endsection
