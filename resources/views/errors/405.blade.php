@extends('errors.layout')

@section('code', '405')
@section('title', __('Not allowed here'))
@section('message'){{ __('This page can\'t be used that way. Go back and try again from the form.') }}@endsection
