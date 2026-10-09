@extends('layouts.app')

@section('title', 'Create Supplier Return')

@section('content')
    <div>
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('purchase-returns.index') }}">Supplier Returns</a></li>
                        <li class="breadcrumb-item active">Create New</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Create Supplier Return</h4>
            </div>
        </div>

        @include('purchase-returns._form', [
            'formAction' => route('purchase-returns.store'),
            'formMethod' => 'POST',
            'submitLabel' => 'Create Supplier Return',
            'cancelUrl' => route('purchase-returns.index'),
            'purchaseReturn' => null,
        ])
    </div>
@endsection
