@extends('layouts.app')

@section('title', 'Order #' . $order->order_number)

@section('content')

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom-dashed">
                    <div class="row align-items-center">
                        <div class="col-sm">
                            <h5 class="card-title mb-0">
                                Order #{{ $order->order_number }}
                                @if ($order->is_converted)
                                    <span class="badge bg-success-subtle text-success ms-2">Converted</span>
                                @endif
                                @if ($order->is_cod)
                                    <span class="badge bg-warning-subtle text-warning ms-2">COD</span>
                                @endif
                            </h5>
                        </div>
                        <div class="col-sm-auto">
                            <div class="d-flex flex-wrap align-items-start gap-2">
                                @if ($order->can_be_converted)
                                    <a href="{{ route('ecommerce-orders.convert', $order) }}" class="btn btn-success">
                                        <iconify-icon icon="solar:cart-plus-bold-duotone"
                                            class="align-middle me-1"></iconify-icon> Convert to Sale
                                    </a>
                                @endif
                                @if ($order->status->canChangeStatus())
                                    @foreach ($order->status->allowedTransitions() as $transition)
                                        <button type="button" class="btn {{ $transition->buttonClass() }}"
                                            data-bs-toggle="modal" data-bs-target="#statusModal-{{ $transition->value }}">
                                            <iconify-icon icon="{{ $transition->icon() }}"
                                                class="align-middle me-1"></iconify-icon>
                                            {{ $transition->label() }}
                                        </button>
                                    @endforeach
                                @endif
                                <a href="{{ route('ecommerce-orders.index') }}" class="btn btn-soft-secondary">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                    Back
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <!-- Order Information -->
                        <div class="col-lg-6">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">Order Information</h6>
                            <div class="table-responsive">
                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="fw-medium">Order #:</td>
                                            <td>{{ $order->order_number }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Platform:</td>
                                            <td>
                                                @if ($order->platform === 'woocommerce')
                                                    <span class="badge bg-purple-subtle text-purple">
                                                        <iconify-icon icon="logos:woocommerce-icon"
                                                            class="align-middle me-1"></iconify-icon> WooCommerce
                                                    </span>
                                                @else
                                                    <span class="badge bg-success-subtle text-success">
                                                        <iconify-icon icon="logos:shopify"
                                                            class="align-middle me-1"></iconify-icon> Shopify
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Shop:</td>
                                            <td>{{ $order->shop->name }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Platform Order ID:</td>
                                            <td><code>{{ $order->platform_order_id }}</code></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Status:</td>
                                            <td>
                                                <span
                                                    class="badge bg-{{ $order->status->color() }}-subtle text-{{ $order->status->color() }}">
                                                    {{ $order->status->label() }}
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Payment Method:</td>
                                            <td>{{ $order->payment_method ?? 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Payment Status:</td>
                                            <td>
                                                @if ($order->payment_status === 'paid')
                                                    <span class="badge bg-success">Paid</span>
                                                @elseif($order->payment_status === 'refunded')
                                                    <span class="badge bg-dark">Refunded</span>
                                                @else
                                                    <span class="badge bg-danger">Unpaid</span>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Order Date:</td>
                                            <td>{{ $order->platform_created_at?->format('M d, Y h:i A') ?? $order->created_at->format('M d, Y h:i A') }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-medium">Last Synced:</td>
                                            <td>{{ $order->last_synced_at?->format('M d, Y h:i A') ?? 'Never' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Customer Information -->
                        <div class="col-lg-6">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">Customer Information</h6>
                            <div class="table-responsive">
                                <table class="table table-borderless table-sm mb-0">
                                    <tbody>
                                        <tr>
                                            <td class="fw-medium">Name:</td>
                                            <td>{{ $order->customer_name ?? 'N/A' }}</td>
                                        </tr>
                                        @if ($order->customer_email)
                                            <tr>
                                                <td class="fw-medium">Email:</td>
                                                <td>{{ $order->customer_email }}</td>
                                            </tr>
                                        @endif
                                        @if ($order->customer_phone)
                                            <tr>
                                                <td class="fw-medium">Phone:</td>
                                                <td>{{ $order->customer_phone }}</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>

                                @if ($order->billing_address)
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2 mt-3">Billing Address</h6>
                                    <p class="text-muted mb-0">
                                        {{ $order->billing_address['address_1'] ?? '' }}
                                        {{ $order->billing_address['address_2'] ?? '' }}<br>
                                        {{ $order->billing_address['city'] ?? '' }}
                                        {{ $order->billing_address['state'] ?? '' }}
                                        {{ $order->billing_address['postcode'] ?? '' }}<br>
                                        {{ $order->billing_address['country'] ?? '' }}
                                    </p>
                                @endif

                                @if ($order->shipping_address)
                                    <h6 class="text-muted text-uppercase fw-semibold mb-2 mt-3">Shipping Address</h6>
                                    <p class="text-muted mb-0">
                                        {{ $order->shipping_address['address_1'] ?? '' }}
                                        {{ $order->shipping_address['address_2'] ?? '' }}<br>
                                        {{ $order->shipping_address['city'] ?? '' }}
                                        {{ $order->shipping_address['state'] ?? '' }}
                                        {{ $order->shipping_address['postcode'] ?? '' }}<br>
                                        {{ $order->shipping_address['country'] ?? '' }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Converted Sale Info -->
                    @if ($order->is_converted)
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-success d-flex align-items-center" role="alert">
                                    <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-24 me-2"></iconify-icon>
                                    <div>
                                        This order was converted to
                                        <a href="{{ route('sales.show', $order->sale) }}" class="fw-semibold alert-link">
                                            Sale #{{ $order->sale->invoice_number }}
                                        </a>
                                        on {{ $order->converted_at->format('M d, Y h:i A') }}
                                        by {{ $order->convertedBy->name ?? 'Unknown' }}.
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Order Items -->
                    <div class="mt-4">
                        <h6 class="text-muted text-uppercase fw-semibold mb-3">Order Items</h6>
                        <div class="table-responsive">
                            <table class="table table-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Product</th>
                                        <th scope="col">SKU</th>
                                        <th scope="col">Matched Product</th>
                                        <th scope="col" class="text-end">Unit Price</th>
                                        <th scope="col" class="text-end">Quantity</th>
                                        <th scope="col" class="text-end">Tax</th>
                                        <th scope="col" class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($order->items as $item)
                                        <tr>
                                            <td>
                                                <div class="fw-medium">{{ $item->name }}</div>
                                            </td>
                                            <td>
                                                <code>{{ $item->sku ?? '—' }}</code>
                                            </td>
                                            <td>
                                                @if ($item->hasMatchedProduct())
                                                    <span class="badge bg-success-subtle text-success">
                                                        <iconify-icon icon="solar:link-bold"
                                                            class="align-middle me-1"></iconify-icon>
                                                        {{ $item->product->name }}
                                                    </span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning">
                                                        <iconify-icon icon="solar:link-broken-bold"
                                                            class="align-middle me-1"></iconify-icon> Unmatched
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                {{ $order->currency }}
                                                {{ number_format($item->unit_price, 2) }}
                                            </td>
                                            <td class="text-end">{{ $item->quantity }}</td>
                                            <td class="text-end">
                                                {{ $order->currency }}
                                                {{ number_format($item->tax_total, 2) }}
                                            </td>
                                            <td class="text-end">
                                                {{ $order->currency }}
                                                {{ number_format($item->total, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="mt-4">
                        <div class="row justify-content-end">
                            <div class="col-lg-4">
                                <div class="table-responsive">
                                    <table class="table table-borderless mb-0">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted">Subtotal:</td>
                                                <td class="text-end">{{ $order->currency }}
                                                    {{ number_format($order->subtotal, 2) }}</td>
                                            </tr>
                                            @if ($order->discount_total > 0)
                                                <tr>
                                                    <td class="text-muted">Discount:</td>
                                                    <td class="text-end text-danger">-{{ $order->currency }}
                                                        {{ number_format($order->discount_total, 2) }}</td>
                                                </tr>
                                            @endif
                                            @if ($order->shipping_total > 0)
                                                <tr>
                                                    <td class="text-muted">Shipping:</td>
                                                    <td class="text-end">{{ $order->currency }}
                                                        {{ number_format($order->shipping_total, 2) }}</td>
                                                </tr>
                                            @endif
                                            @if ($order->tax_total > 0)
                                                <tr>
                                                    <td class="text-muted">Tax:</td>
                                                    <td class="text-end">{{ $order->currency }}
                                                        {{ number_format($order->tax_total, 2) }}</td>
                                                </tr>
                                            @endif
                                            <tr class="border-top">
                                                <th class="fs-16">Total:</th>
                                                <th class="text-end fs-16 text-primary">{{ $order->currency }}
                                                    {{ number_format($order->total, 2) }}</th>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($order->notes)
                        <div class="mt-4">
                            <h6 class="text-muted text-uppercase fw-semibold mb-2">Platform Notes</h6>
                            <p class="text-muted mb-0">{{ $order->notes }}</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Notes & Reminders -->
    <div class="row mt-3">
        <!-- Order Notes -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header border-bottom-dashed d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">
                        <iconify-icon icon="solar:notes-bold-duotone" class="align-middle me-1 text-info"></iconify-icon>
                        Notes
                    </h5>
                    <button type="button" class="btn btn-sm btn-soft-info" data-bs-toggle="modal"
                        data-bs-target="#addNoteModal">
                        <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon> Add
                        Note
                    </button>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    @forelse ($order->orderNotes as $note)
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <div class="avatar-xs">
                                    <div class="avatar-title rounded-circle bg-info-subtle text-info">
                                        <iconify-icon icon="solar:user-bold-duotone"></iconify-icon>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <p class="text-muted mb-1">{{ $note->message }}</p>
                                <small class="text-muted">
                                    {{ $note->creator?->name ?? 'System' }} &bull;
                                    {{ $note->created_at->diffForHumans() }}
                                </small>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <iconify-icon icon="solar:notes-bold-duotone" class="fs-36 mb-2 d-block"></iconify-icon>
                            <p class="mb-0">No notes yet. Add one to keep track of this order.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Reminders -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header border-bottom-dashed d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">
                        <iconify-icon icon="solar:alarm-bold-duotone"
                            class="align-middle me-1 text-warning"></iconify-icon>
                        Reminders
                    </h5>
                    <button type="button" class="btn btn-sm btn-soft-warning" data-bs-toggle="modal"
                        data-bs-target="#addReminderModal">
                        <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon> Add
                        Reminder
                    </button>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    @forelse ($order->reminders as $reminder)
                        <div class="d-flex mb-3 align-items-start">
                            <div class="flex-shrink-0">
                                <div class="avatar-xs">
                                    <div
                                        class="avatar-title rounded-circle {{ $reminder->is_resolved ? 'bg-success-subtle text-success' : ($reminder->isOverdue() ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning') }}">
                                        <iconify-icon
                                            icon="{{ $reminder->is_resolved ? 'solar:check-circle-bold-duotone' : ($reminder->isOverdue() ? 'solar:alarm-bold-duotone' : 'solar:clock-circle-bold-duotone') }}"></iconify-icon>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">
                                    {{ $reminder->title }}
                                    @if ($reminder->is_resolved)
                                        <span class="badge bg-success-subtle text-success ms-1">Resolved</span>
                                    @elseif ($reminder->isOverdue())
                                        <span class="badge bg-danger-subtle text-danger ms-1">Overdue</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning ms-1">Upcoming</span>
                                    @endif
                                </h6>
                                @if ($reminder->message)
                                    <p class="text-muted mb-1 small">{{ $reminder->message }}</p>
                                @endif
                                <small class="text-muted">
                                    <iconify-icon icon="solar:clock-circle-linear" class="align-middle"></iconify-icon>
                                    {{ $reminder->scheduled_at->format('M d, Y h:i A') }}
                                    &bull; {{ $reminder->creator?->name ?? 'System' }}
                                </small>
                                @if (!$reminder->is_resolved)
                                    <form method="POST"
                                        action="{{ route('ecommerce-orders.resolveReminder', [$order, $reminder]) }}"
                                        class="d-inline ms-2">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-soft-success py-0 px-1"
                                            title="Dismiss">
                                            <iconify-icon icon="solar:check-circle-bold-duotone"
                                                class="align-middle"></iconify-icon> Dismiss
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <iconify-icon icon="solar:alarm-bold-duotone" class="fs-36 mb-2 d-block"></iconify-icon>
                            <p class="mb-0">No reminders set. Add one to stay on top of this order.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div class="modal fade" id="addNoteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('ecommerce-orders.storeNote', $order) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:notes-bold-duotone"
                                class="align-middle me-1 text-info"></iconify-icon>
                            Add Note
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-0">
                            <label for="note-message" class="form-label">Note</label>
                            <textarea name="message" id="note-message" class="form-control" rows="4"
                                placeholder="Write your note about this order..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-info">
                            <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Save Note
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Reminder Modal -->
    <div class="modal fade" id="addReminderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('ecommerce-orders.storeReminder', $order) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <iconify-icon icon="solar:alarm-bold-duotone"
                                class="align-middle me-1 text-warning"></iconify-icon>
                            Set Reminder
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="reminder-title" class="form-label">Title <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="title" id="reminder-title" class="form-control"
                                placeholder="e.g. Follow up on delivery" required>
                        </div>
                        <div class="mb-3">
                            <label for="reminder-message" class="form-label">Details</label>
                            <textarea name="message" id="reminder-message" class="form-control" rows="3"
                                placeholder="Additional details about this reminder..."></textarea>
                        </div>
                        <div class="mb-0">
                            <label for="reminder-scheduled-at" class="form-label">Remind At <span
                                    class="text-danger">*</span></label>
                            <input type="datetime-local" name="scheduled_at" id="reminder-scheduled-at"
                                class="form-control" required min="{{ now()->format('Y-m-d\TH:i') }}">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-warning">
                            <iconify-icon icon="solar:alarm-bold-duotone" class="align-middle me-1"></iconify-icon>
                            Set Reminder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Status Change Modals -->
    @if ($order->status->canChangeStatus())
        @foreach ($order->status->allowedTransitions() as $transition)
            <div class="modal fade" id="statusModal-{{ $transition->value }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('ecommerce-orders.updateStatus', $order) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $transition->value }}">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <iconify-icon icon="{{ $transition->icon() }}"
                                        class="align-middle me-1 text-{{ $transition->color() }}"></iconify-icon>
                                    {{ $transition->label() }} Order
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p class="text-muted">
                                    Change order <strong>#{{ $order->order_number }}</strong> status from
                                    <span
                                        class="badge bg-{{ $order->status->color() }}-subtle text-{{ $order->status->color() }}">{{ $order->status->label() }}</span>
                                    to
                                    <span
                                        class="badge bg-{{ $transition->color() }}-subtle text-{{ $transition->color() }}">{{ $transition->label() }}</span>.
                                    This will also update the status on the website.
                                </p>
                                <div class="mb-3">
                                    <label for="note-{{ $transition->value }}" class="form-label">Note (Optional)</label>
                                    <textarea name="note" id="note-{{ $transition->value }}" class="form-control" rows="3"
                                        placeholder="Add a reason or note for this status change..."></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn {{ $transition->buttonClass() }}">
                                    <iconify-icon icon="{{ $transition->icon() }}"
                                        class="align-middle me-1"></iconify-icon>
                                    {{ $transition->label() }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

@endsection
