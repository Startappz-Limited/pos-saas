@extends('errors.layout')

@section('code', '413')
@section('title', __('Too large'))
@section('message'){{ __('What you sent is too large. Try a smaller file.') }}@endsection
