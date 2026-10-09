@extends('layouts.app')

@section('title', 'Edit Supplier Return: ' . $purchaseReturn->return_number)

@section('content')
    <div>
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('purchase-returns.index') }}">Supplier Returns</a></li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('purchase-returns.show', $purchaseReturn) }}">{{ $purchaseReturn->return_number }}</a>
                        </li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Edit Supplier Return</h4>
            </div>
        </div>

        @include('purchase-returns._form', [
            'formAction' => route('purchase-returns.update', $purchaseReturn),
            'formMethod' => 'PUT',
            'submitLabel' => 'Update Supplier Return',
            'cancelUrl' => route('purchase-returns.show', $purchaseReturn),
        ])
    </div>
@endsection
