@extends('layouts.app')

@section('title', 'Start Baileys Session')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Start Baileys Session</h4>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('baileys.sessions.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Shop</label>
                    <select name="shop_id" class="form-select @error('shop_id') is-invalid @enderror" required>
                        <option value="">Select shop</option>
                        @foreach ($shops as $shop)
                            <option value="{{ $shop->id }}" @selected(old('shop_id') == $shop->id)>{{ $shop->name }}</option>
                        @endforeach
                    </select>
                    @error('shop_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Session Name</label>
                    <input name="name" value="{{ old('name', 'Default') }}"
                        class="form-control @error('name') is-invalid @enderror">
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <button class="btn btn-primary">Start &amp; Generate QR</button>
                <a href="{{ route('baileys.sessions.index') }}" class="btn btn-link">Cancel</a>
            </form>
        </div>
    </div>
@endsection
