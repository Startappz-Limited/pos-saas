@extends('layouts.app')

@section('title', 'Edit Return: ' . $saleReturn->return_number)

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('returns.index') }}">Returns</a></li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('returns.show', $saleReturn) }}">{{ $saleReturn->return_number }}</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Edit Return: {{ $saleReturn->return_number }}</h4>
            </div>
        </div>

        <form action="{{ route('returns.update', $saleReturn) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-xl-8">
                    <!-- Return Details -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Return Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Sale</label>
                                    <input type="text" class="form-control" disabled
                                        value="{{ $saleReturn->sale?->invoice_number ?? '#' . $saleReturn->sale_id }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                                    <select name="reason" class="form-select @error('reason') is-invalid @enderror"
                                        required>
                                        @foreach ($returnReasons as $reason)
                                            <option value="{{ $reason->value }}"
                                                {{ old('reason', $saleReturn->reason->value) == $reason->value ? 'selected' : '' }}>
                                                {{ $reason->label() }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Restocking Fee</label>
                                    <input type="number" name="restocking_fee" step="0.01" min="0"
                                        class="form-control @error('restocking_fee') is-invalid @enderror"
                                        value="{{ old('restocking_fee', $saleReturn->restocking_fee) }}">
                                    @error('restocking_fee')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2">{{ old('notes', $saleReturn->notes) }}</textarea>
                                    @error('notes')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Return Items -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Return Items</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Product</th>
                                            <th style="width: 120px;">Quantity</th>
                                            <th style="width: 140px;">Condition</th>
                                            <th style="width: 200px;">Notes</th>
                                            <th style="width: 180px;">Supplier Return</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($saleReturn->sale->items as $index => $saleItem)
                                            @php
                                                $existingReturnItem = $saleReturn->items->firstWhere(
                                                    'sale_item_id',
                                                    $saleItem->id,
                                                );
                                            @endphp
                                            @if ($existingReturnItem)
                                                <tr>
                                                    <td>{{ $saleItem->product?->name ?? 'Unknown' }}</td>
                                                    <td>
                                                        <input type="hidden"
                                                            name="items[{{ $index }}][sale_item_id]"
                                                            value="{{ $saleItem->id }}">
                                                        <input type="number" name="items[{{ $index }}][quantity]"
                                                            class="form-control form-control-sm" min="1"
                                                            max="{{ $saleItem->quantity }}"
                                                            value="{{ old("items.{$index}.quantity", $existingReturnItem->quantity) }}">
                                                    </td>
                                                    <td>
                                                        <select name="items[{{ $index }}][condition]"
                                                            class="form-select form-select-sm">
                                                            <option value="">—</option>
                                                            @foreach (['new', 'opened', 'damaged', 'defective'] as $condition)
                                                                <option value="{{ $condition }}"
                                                                    {{ old("items.{$index}.condition", $existingReturnItem->condition) === $condition ? 'selected' : '' }}>
                                                                    {{ ucfirst($condition) }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                            name="items[{{ $index }}][condition_notes]"
                                                            class="form-control form-control-sm"
                                                            value="{{ old("items.{$index}.condition_notes", $existingReturnItem->condition_notes) }}">
                                                    </td>
                                                    <td>
                                                        <div class="form-check mb-2">
                                                            <input type="checkbox"
                                                                name="items[{{ $index }}][return_to_supplier]"
                                                                value="1" class="form-check-input"
                                                                {{ old("items.{$index}.return_to_supplier", $existingReturnItem->return_to_supplier) ? 'checked' : '' }}>
                                                            <label class="form-check-label small">Mark for supplier</label>
                                                        </div>
                                                        <input type="text"
                                                            name="items[{{ $index }}][return_to_supplier_notes]"
                                                            class="form-control form-control-sm" placeholder="Supplier note"
                                                            value="{{ old("items.{$index}.return_to_supplier_notes", $existingReturnItem->return_to_supplier_notes) }}">
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="solar:check-circle-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Update Return
                                </button>
                                <a href="{{ route('returns.show', $saleReturn) }}" class="btn btn-light">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection
