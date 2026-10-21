@extends('errors.layout')

@section('code', '401')
@section('title', __('Sign in needed'))
@section('message'){{ __('Please sign in to continue.') }}@endsection
