@extends('layouts.app')

@section('title', 'Edit Stock Intake: ' . $stockIntake->intake_number)

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('stock-intakes.index') }}">Stock Intakes</a></li>
                        <li class="breadcrumb-item"><a
                                href="{{ route('stock-intakes.show', $stockIntake) }}">{{ $stockIntake->intake_number }}</a>
                        </li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Edit Stock Intake: {{ $stockIntake->intake_number }}</h4>
            </div>
        </div>

        <form action="{{ route('stock-intakes.update', $stockIntake) }}" method="POST" id="stock-intake-form">
            @csrf
            @method('PUT')
            <div class="row">
                <div class="col-xl-8">
                    <!-- Purchase Order Info (if linked) -->
                    @if ($stockIntake->purchaseOrder)
                        <div class="card mb-4">
                            <div class="card-header bg-primary-subtle">
                                <h5 class="card-title mb-0">
                                    <iconify-icon icon="solar:clipboard-list-bold-duotone"
                                        class="align-middle me-1"></iconify-icon>
                                    Linked to Purchase Order: {{ $stockIntake->purchaseOrder->order_number }}
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <span class="text-muted">Supplier:</span>
                                            <strong>{{ $stockIntake->purchaseOrder->supplier?->name ?? 'N/A' }}</strong>
                                        </p>
                                        <p class="mb-1">
                                            <span class="text-muted">Shop:</span>
                                            <strong>{{ $stockIntake->purchaseOrder->shop?->name ?? 'N/A' }}</strong>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <span class="text-muted">Order Date:</span>
                                            <strong>{{ $stockIntake->purchaseOrder->order_date?->format('M d, Y') ?? 'N/A' }}</strong>
                                        </p>
                                    </div>
                                </div>

                                @if ($stockIntake->purchaseOrderItem)
                                    <hr class="my-3">
                                    <div class="alert alert-info mb-0">
                                        <strong>Item:</strong> {{ $stockIntake->purchaseOrderItem->product_name }}
                                        @if ($stockIntake->purchaseOrderItem->variation_attributes)
                                            <br>
                                            <small>
                                                @foreach ($stockIntake->purchaseOrderItem->variation_attributes as $attr => $value)
                                                    {{ ucfirst($attr) }}: {{ $value }}
                                                    @if (!$loop->last)
                                                        |
                                                    @endif
                                                @endforeach
                                            </small>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- Product Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Product Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Product</label>
                                    <input type="text" class="form-control" value="{{ $stockIntake->product?->name ?? 'N/A' }}" readonly>
                                    @if ($stockIntake->productVariation)
                                        <small class="text-muted">Variation: {{ $stockIntake->productVariation->name }}</small>
                                    @endif
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Shop</label>
                                    <input type="text" class="form-control" value="{{ $stockIntake->shop?->name ?? 'N/A' }}" readonly>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quantity & Quality -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Quantity & Quality Check</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Quantity Received <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity_received"
                                        class="form-control @error('quantity_received') is-invalid @enderror"
                                        value="{{ old('quantity_received', $stockIntake->quantity_received) }}"
                                        min="0" step="0.01" required>
                                    @error('quantity_received')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Quantity Accepted <span class="text-danger">*</span></label>
                                    <input type="number" name="quantity_accepted"
                                        class="form-control @error('quantity_accepted') is-invalid @enderror"
                                        value="{{ old('quantity_accepted', $stockIntake->quantity_accepted) }}"
                                        min="0" step="0.01" required>
                                    @error('quantity_accepted')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Quantity Rejected</label>
                                    <input type="number" name="quantity_rejected"
                                        class="form-control @error('quantity_rejected') is-invalid @enderror"
                                        value="{{ old('quantity_rejected', $stockIntake->quantity_rejected) }}" min="0" step="0.01">
                                    @error('quantity_rejected')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Unit</label>
                                    <input type="text" name="unit"
                                        class="form-control @error('unit') is-invalid @enderror"
                                        value="{{ old('unit', $stockIntake->unit) }}"
                                        placeholder="e.g., pcs, kg, liters">
                                    @error('unit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Quality Status</label>
                                    <select name="quality_status"
                                        class="form-select @error('quality_status') is-invalid @enderror">
                                        <option value="passed" {{ old('quality_status', $stockIntake->quality_status) === 'passed' ? 'selected' : '' }}>
                                            Passed</option>
                                        <option value="partial"
                                            {{ old('quality_status', $stockIntake->quality_status) === 'partial' ? 'selected' : '' }}>Partial (Some
                                            Issues)</option>
                                        <option value="failed" {{ old('quality_status', $stockIntake->quality_status) === 'failed' ? 'selected' : '' }}>
                                            Failed</option>
                                    </select>
                                    @error('quality_status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Quality Notes</label>
                                <textarea name="quality_notes" class="form-control @error('quality_notes') is-invalid @enderror" rows="2"
                                    placeholder="Describe any quality issues, damages, or discrepancies...">{{ old('quality_notes', $stockIntake->quality_notes) }}</textarea>
                                @error('quality_notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Storage & Batch Info -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Storage & Batch Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Batch Number</label>
                                    <input type="text" name="batch_number"
                                        class="form-control @error('batch_number') is-invalid @enderror"
                                        value="{{ old('batch_number', $stockIntake->batch_number) }}" placeholder="e.g., BATCH-2024-001">
                                    @error('batch_number')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Expiry Date</label>
                                    <input type="date" name="expiry_date"
                                        class="form-control @error('expiry_date') is-invalid @enderror"
                                        value="{{ old('expiry_date', $stockIntake->expiry_date?->format('Y-m-d')) }}">
                                    @error('expiry_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Intake Date</label>
                                    <input type="date" name="intake_date"
                                        class="form-control @error('intake_date') is-invalid @enderror"
                                        value="{{ old('intake_date', $stockIntake->intake_date?->format('Y-m-d')) }}">
                                    @error('intake_date')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Storage Location</label>
                                    <input type="text" name="storage_location"
                                        class="form-control @error('storage_location') is-invalid @enderror"
                                        value="{{ old('storage_location', $stockIntake->storage_location) }}" placeholder="e.g., Warehouse A">
                                    @error('storage_location')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Bin Location</label>
                                    <input type="text" name="bin_location"
                                        class="form-control @error('bin_location') is-invalid @enderror"
                                        value="{{ old('bin_location', $stockIntake->bin_location) }}" placeholder="e.g., A1-B2-C3">
                                    @error('bin_location')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2"
                                    placeholder="Any additional notes about this intake...">{{ old('notes', $stockIntake->notes) }}</textarea>
                                @error('notes')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-xl-4">
                    <!-- Current Status -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Current Status</h5>
                        </div>
                        <div class="card-body">
                            @php
                                $statusColors = [
                                    'pending' => 'warning',
                                    'in_progress' => 'info',
                                    'completed' => 'success',
                                    'cancelled' => 'danger',
                                ];
                                $color = $statusColors[$stockIntake->status->value] ?? 'secondary';
                            @endphp
                            <div class="text-center">
                                <span class="badge bg-{{ $color }}-subtle text-{{ $color }} fs-14 px-3 py-2">
                                    {{ $stockIntake->status->label() }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Actions Card -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <iconify-icon icon="solar:diskette-bold-duotone" class="align-middle me-1"></iconify-icon>
                                    Save Changes
                                </button>
                                
                                @if ($stockIntake->status->canComplete())
                                    <button type="submit" name="action" value="save_complete" class="btn btn-success">
                                        <iconify-icon icon="solar:check-circle-bold-duotone"
                                            class="align-middle me-1"></iconify-icon>
                                        Save & Complete Intake
                                    </button>
                                @endif

                                <a href="{{ route('stock-intakes.show', $stockIntake) }}" class="btn btn-light">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Info -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Intake Info</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted ps-0">Created:</td>
                                    <td>{{ $stockIntake->created_at->format('M d, Y H:i') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">Created By:</td>
                                    <td>{{ $stockIntake->creator?->name ?? '—' }}</td>
                                </tr>
                                @if ($stockIntake->received_at)
                                    <tr>
                                        <td class="text-muted ps-0">Received At:</td>
                                        <td>{{ $stockIntake->received_at->format('M d, Y H:i') }}</td>
                                    </tr>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Auto-calculate rejected quantity
                const qtyReceived = document.querySelector('input[name="quantity_received"]');
                const qtyAccepted = document.querySelector('input[name="quantity_accepted"]');
                const qtyRejected = document.querySelector('input[name="quantity_rejected"]');

                function calculateRejected() {
                    const received = parseFloat(qtyReceived.value) || 0;
                    const accepted = parseFloat(qtyAccepted.value) || 0;
                    qtyRejected.value = Math.max(0, received - accepted);
                }

                if (qtyReceived && qtyAccepted && qtyRejected) {
                    qtyReceived.addEventListener('change', calculateRejected);
                    qtyAccepted.addEventListener('change', calculateRejected);
                }
            });
        </script>
    @endpush
@endsection
