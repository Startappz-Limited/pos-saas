@extends('errors.layout')

@section('code', '419')
@section('title', __('Page expired'))
@section('message'){{ __('Your session expired, so the form wasn\'t sent. Reload the page and try again.') }}@endsection
