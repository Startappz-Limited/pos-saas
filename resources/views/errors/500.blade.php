@extends('errors.layout')

@section('code', '500')
@section('title', __('Something went wrong'))
@section('message'){{ __('An error on our side stopped this from working, and nothing was saved. Please try again; if it keeps happening, contact support.') }}@endsection
