@extends('errors.layout')

@section('code', '403')
@section('title', __('Not allowed'))
@section('message'){{ $exception->getMessage() !== '' ? $exception->getMessage() : __('You don\'t have permission to do this. If you think you should, ask an owner of the business.') }}@endsection
