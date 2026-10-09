@extends('layouts.app')

@section('title', __('Abandoned Cart') . ' #' . $cart->platform_cart_id)

@section('content')

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header border-bottom-dashed">
                    <div class="row align-items-center">
                        <div class="col-sm">
                            <h5 class="card-title mb-0">
                                {{ __('Abandoned Cart') }} #{{ $cart->platform_cart_id }}
                                <span class="badge bg-{{ $cart->status->color() }}-subtle text-{{ $cart->status->color() }} ms-2">
                                    {{ $cart->status->label() }}
                                </span>
                                @if ($cart->opted_out_at)
                                    <span class="badge bg-dark-subtle text-dark ms-1">{{ __('Opted out') }}</span>
                                @endif
                            </h5>
                        </div>
                        <div class="col-sm-auto">
                            <div class="d-flex flex-wrap align-items-start gap-2">
                                @can('convert', $cart)
                                    @if ($cart->can_be_converted)
                                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#convertModal"
                                            @disabled($cart->has_unmatched_items)
                                            title="{{ $cart->has_unmatched_items ? __('Some items are not linked to a POS product') : '' }}">
                                            <iconify-icon icon="solar:cart-plus-bold-duotone" class="align-middle me-1"></iconify-icon>
                                            {{ __('Convert to Sale') }}
                                        </button>
                                    @endif
                                @endcan
                                @can('contact', $cart)
                                    @if (! $cart->opted_out_at && $cart->can_be_converted)
                                        <button type="button" class="btn btn-soft-success" data-bs-toggle="modal" data-bs-target="#whatsappModal"
                                            @disabled(! $cart->customer_phone)>
                                            <iconify-icon icon="ri:whatsapp-fill" class="align-middle me-1"></iconify-icon>
                                            {{ __('Send WhatsApp') }}
                                        </button>
                                    @endif
                                @endcan
                                @can('update', $cart)
                                    @if (! $cart->opted_out_at)
                                        <button type="button" class="btn btn-soft-dark" data-bs-toggle="modal" data-bs-target="#optOutModal">
                                            <iconify-icon icon="solar:bell-off-bold-duotone" class="align-middle me-1"></iconify-icon>
                                            {{ __('Customer opted out') }}
                                        </button>
                                    @endif
                                @endcan
                                <a href="{{ route('abandoned-carts.index') }}" class="btn btn-soft-secondary">
                                    <iconify-icon icon="solar:arrow-left-linear" class="align-middle me-1"></iconify-icon>
                                    {{ __('Back') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <!-- Cart Information -->
                        <div class="col-lg-6">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">{{ __('Cart Information') }}</h6>
                            <table class="table table-borderless table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td class="fw-medium">{{ __('Shop') }}:</td>
                                        <td>{{ $cart->shop->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">{{ __('Website cart #') }}:</td>
                                        <td><code>{{ $cart->platform_cart_id }}</code></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">{{ __('Website status') }}:</td>
                                        <td>{{ $cart->platform_status ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">{{ __('Captured via') }}:</td>
                                        <td>{{ $cart->capture_source ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">{{ __('Abandoned') }}:</td>
                                        <td>{{ $cart->abandoned_at?->format('M d, Y h:i A') ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">{{ __('Reminders sent') }}:</td>
                                        <td>{{ $cart->reminders_sent }}
                                            @if ($cart->last_activity)
                                                <small class="text-muted">({{ __('last') }}: {{ str_replace('_', ' ', $cart->last_activity) }})</small>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($cart->last_contacted_at)
                                        <tr>
                                            <td class="fw-medium">{{ __('Last contacted') }}:</td>
                                            <td>{{ $cart->last_contacted_at->format('M d, Y h:i A') }}</td>
                                        </tr>
                                    @endif
                                    @if ($cart->coupon_code)
                                        <tr>
                                            <td class="fw-medium">{{ __('Coupon') }}:</td>
                                            <td><code>{{ $cart->coupon_code }}</code></td>
                                        </tr>
                                    @endif
                                    @if ($cart->recovered_at)
                                        <tr>
                                            <td class="fw-medium">{{ __('Recovered') }}:</td>
                                            <td>
                                                {{ $cart->recovered_at->format('M d, Y h:i A') }}
                                                @if ($cart->ecommerceOrder)
                                                    — <a href="{{ route('ecommerce-orders.show', $cart->ecommerceOrder) }}">{{ __('Order') }} {{ $cart->ecommerceOrder->order_number }}</a>
                                                @elseif ($cart->platform_order_id)
                                                    — {{ __('website order') }} #{{ $cart->platform_order_id }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <!-- Customer Information -->
                        <div class="col-lg-6">
                            <h6 class="text-muted text-uppercase fw-semibold mb-3">{{ __('Customer Information') }}</h6>
                            <table class="table table-borderless table-sm mb-0">
                                <tbody>
                                    <tr>
                                        <td class="fw-medium">{{ __('Name') }}:</td>
                                        <td>{{ $cart->customer_name ?? __('Unknown') }}
                                            @if ($cart->user_type)
                                                <span class="badge bg-light text-dark ms-1">{{ ucfirst(strtolower($cart->user_type)) }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($cart->customer_email)
                                        <tr>
                                            <td class="fw-medium">{{ __('Email') }}:</td>
                                            <td>{{ $cart->customer_email }}</td>
                                        </tr>
                                    @endif
                                    @if ($cart->customer_phone)
                                        <tr>
                                            <td class="fw-medium">{{ __('Phone') }}:</td>
                                            <td>{{ $cart->customer_phone }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td class="fw-medium">{{ __('POS customer') }}:</td>
                                        <td>
                                            @if ($cart->customer)
                                                <a href="{{ route('customers.show', $cart->customer) }}">{{ $cart->customer->name }}</a>
                                            @else
                                                <span class="text-muted">{{ __('Not matched') }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-medium">{{ __('Assigned to') }}:</td>
                                        <td>{{ $cart->assignee->name ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>

                            @can('update', $cart)
                                @unless ($cart->status->isFinal())
                                    <form method="POST" action="{{ route('abandoned-carts.update', $cart) }}" class="row g-2 mt-2">
                                        @csrf
                                        @honeypot
                                        <div class="col-sm-5">
                                            <select name="status" class="form-select form-select-sm" aria-label="{{ __('Status') }}">
                                                @foreach (\App\Enums\AbandonedCartStatus::manual() as $status)
                                                    <option value="{{ $status->value }}" @selected($cart->status === $status)>{{ $status->label() }}</option>
                                                @endforeach
                                                @unless (in_array($cart->status, \App\Enums\AbandonedCartStatus::manual(), true))
                                                    <option value="" selected>{{ $cart->status->label() }}</option>
                                                @endunless
                                            </select>
                                        </div>
                                        <div class="col-sm-5">
                                            <select name="assigned_to" class="form-select form-select-sm" aria-label="{{ __('Assigned to') }}">
                                                <option value="">{{ __('Unassigned') }}</option>
                                                @foreach ($staff as $member)
                                                    <option value="{{ $member->id }}" @selected($cart->assigned_to === $member->id)>{{ $member->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-sm-2">
                                            <button type="submit" class="btn btn-sm btn-primary w-100">{{ __('Save') }}</button>
                                        </div>
                                    </form>
                                @endunless
                            @endcan
                        </div>
                    </div>

                    <!-- Recovery link: live, restores the customer's cart. Only on this page. -->
                    @if ($canSeeLink && $cart->checkout_link && $cart->can_be_converted)
                        <div class="mt-4">
                            <h6 class="text-muted text-uppercase fw-semibold mb-2">{{ __('Recovery link') }}</h6>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace small" readonly id="recovery-link"
                                    value="{{ $cart->checkout_link }}" aria-label="{{ __('Recovery link') }}">
                                <button class="btn btn-outline-secondary" type="button" id="copy-recovery-link" title="{{ __('Copy') }}">
                                    <iconify-icon icon="solar:copy-linear" class="align-middle"></iconify-icon>
                                    <span class="copy-label">{{ __('Copy') }}</span>
                                </button>
                            </div>
                            <small class="text-muted">
                                {{ __('This link restores the customer\'s cart. Share it only with this customer.') }}
                            </small>
                        </div>
                    @endif

                    @if ($cart->sale)
                        <div class="alert alert-success d-flex align-items-center mt-4" role="alert">
                            <iconify-icon icon="solar:check-circle-bold-duotone" class="fs-24 me-2"></iconify-icon>
                            <div>
                                {{ __('Converted to') }}
                                <a href="{{ route('sales.show', $cart->sale) }}" class="fw-semibold alert-link">{{ __('Sale') }} #{{ $cart->sale->invoice_number }}</a>
                                {{ __('on') }} {{ $cart->converted_at?->format('M d, Y h:i A') }}
                                {{ __('by') }} {{ $cart->convertedBy->name ?? __('Unknown') }}.
                            </div>
                        </div>
                    @endif

                    @if ($cart->has_unmatched_items && $cart->can_be_converted)
                        <div class="alert alert-warning mt-4 mb-0" role="alert">
                            {{ __('Some items are not linked to a POS product, so this cart cannot be converted yet. Link the website product to a POS product (or give them matching SKUs); the next update from the website will match it.') }}
                        </div>
                    @endif

                    <!-- Items -->
                    <div class="mt-4">
                        <h6 class="text-muted text-uppercase fw-semibold mb-3">{{ __('Cart Items') }}</h6>
                        <div class="table-responsive">
                            <table class="table table-nowrap align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">{{ __('Product') }}</th>
                                        <th scope="col">{{ __('SKU') }}</th>
                                        <th scope="col">{{ __('Matched Product') }}</th>
                                        <th scope="col" class="text-end">{{ __('Unit Price') }}</th>
                                        <th scope="col" class="text-end">{{ __('Quantity') }}</th>
                                        <th scope="col" class="text-end">{{ __('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($cart->items as $item)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    @if ($item->image_url)
                                                        <img src="{{ $item->image_url }}" alt="" class="rounded" width="36" height="36" loading="lazy" referrerpolicy="no-referrer">
                                                    @endif
                                                    <span class="fw-medium">{{ $item->name }}</span>
                                                </div>
                                            </td>
                                            <td><code>{{ $item->sku ?? '—' }}</code></td>
                                            <td>
                                                @if ($item->isSellable())
                                                    <span class="badge bg-success-subtle text-success">
                                                        <iconify-icon icon="solar:link-bold" class="align-middle me-1"></iconify-icon>
                                                        {{ $item->product->name }}{{ $item->variation ? ' — ' . $item->variation->name : '' }}
                                                    </span>
                                                @elseif ($item->hasMatchedProduct())
                                                    <span class="badge bg-warning-subtle text-warning">{{ __('Variation not matched') }}</span>
                                                @else
                                                    <span class="badge bg-warning-subtle text-warning">
                                                        <iconify-icon icon="solar:link-broken-bold" class="align-middle me-1"></iconify-icon> {{ __('Unmatched') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="text-end">{{ $cart->currency }} {{ number_format((float) $item->unit_price, 2) }}</td>
                                            <td class="text-end">{{ $item->quantity }}</td>
                                            <td class="text-end">{{ $cart->currency }} {{ number_format((float) $item->line_total, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row justify-content-end mt-4">
                        <div class="col-lg-4">
                            <table class="table table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-muted">{{ __('Subtotal') }}:</td>
                                        <td class="text-end">{{ $cart->currency }} {{ number_format((float) $cart->subtotal, 2) }}</td>
                                    </tr>
                                    @if ((float) $cart->tax_total > 0)
                                        <tr>
                                            <td class="text-muted">{{ __('Tax') }}:</td>
                                            <td class="text-end">{{ $cart->currency }} {{ number_format((float) $cart->tax_total, 2) }}</td>
                                        </tr>
                                    @endif
                                    <tr class="border-top">
                                        <th class="fs-16">{{ __('Cart total') }}:</th>
                                        <th class="text-end fs-16 text-primary">{{ $cart->currency }} {{ number_format((float) $cart->total, 2) }}</th>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Timeline & Reminders -->
    <div class="row mt-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header border-bottom-dashed d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">
                        <iconify-icon icon="solar:history-bold-duotone" class="align-middle me-1 text-info"></iconify-icon>
                        {{ __('Timeline') }}
                    </h5>
                    @can('update', $cart)
                        <button type="button" class="btn btn-sm btn-soft-info" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                            <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon> {{ __('Add Note') }}
                        </button>
                    @endcan
                </div>
                <div class="card-body" style="max-height: 480px; overflow-y: auto;">
                    @forelse ($cart->timeline as $entry)
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <div class="avatar-xs">
                                    <div class="avatar-title rounded-circle {{ $entry->type === \App\Enums\AlertType::CART_NOTE ? 'bg-info-subtle text-info' : ($entry->severity->value === 'low' ? 'bg-light text-muted' : 'bg-warning-subtle text-warning') }}">
                                        <iconify-icon icon="{{ $entry->type->icon() }}"></iconify-icon>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1">{{ $entry->title }}</h6>
                                @if ($entry->message)
                                    <p class="text-muted mb-1 small">{{ $entry->message }}</p>
                                @endif
                                <small class="text-muted">
                                    {{ $entry->creator?->name ?? __('Website') }} &bull; {{ $entry->created_at->diffForHumans() }}
                                </small>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <iconify-icon icon="solar:history-bold-duotone" class="fs-36 mb-2 d-block"></iconify-icon>
                            <p class="mb-0">{{ __('Nothing has happened to this cart yet.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header border-bottom-dashed d-flex align-items-center">
                    <h5 class="card-title mb-0 flex-grow-1">
                        <iconify-icon icon="solar:alarm-bold-duotone" class="align-middle me-1 text-warning"></iconify-icon>
                        {{ __('Follow-up Reminders') }}
                    </h5>
                    @can('update', $cart)
                        <button type="button" class="btn btn-sm btn-soft-warning" data-bs-toggle="modal" data-bs-target="#addReminderModal">
                            <iconify-icon icon="solar:add-circle-bold-duotone" class="align-middle me-1"></iconify-icon> {{ __('Add Reminder') }}
                        </button>
                    @endcan
                </div>
                <div class="card-body" style="max-height: 480px; overflow-y: auto;">
                    @forelse ($cart->reminders as $reminder)
                        <div class="d-flex mb-3 align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    {{ $reminder->title }}
                                    @if ($reminder->is_resolved)
                                        <span class="badge bg-success-subtle text-success ms-1">{{ __('Resolved') }}</span>
                                    @elseif ($reminder->isOverdue())
                                        <span class="badge bg-danger-subtle text-danger ms-1">{{ __('Overdue') }}</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning ms-1">{{ __('Upcoming') }}</span>
                                    @endif
                                </h6>
                                @if ($reminder->message)
                                    <p class="text-muted mb-1 small">{{ $reminder->message }}</p>
                                @endif
                                <small class="text-muted">
                                    {{ $reminder->scheduled_at?->format('M d, Y h:i A') }} &bull; {{ $reminder->creator?->name ?? __('System') }}
                                </small>
                                @if (! $reminder->is_resolved)
                                    @can('update', $cart)
                                        <form method="POST" action="{{ route('abandoned-carts.resolveReminder', [$cart, $reminder]) }}" class="d-inline ms-2">
                                            @csrf
                                            @honeypot
                                            <button type="submit" class="btn btn-sm btn-soft-success py-0 px-1">{{ __('Dismiss') }}</button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <iconify-icon icon="solar:alarm-bold-duotone" class="fs-36 mb-2 d-block"></iconify-icon>
                            <p class="mb-0">{{ __('No reminders set. Add one to follow up with this customer.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    @can('update', $cart)
        <!-- Add Note Modal -->
        <div class="modal fade" id="addNoteModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('abandoned-carts.storeNote', $cart) }}">
                        @csrf
                        @honeypot
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Add Note') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <label for="note-message" class="form-label">{{ __('Note') }}</label>
                            <textarea name="message" id="note-message" class="form-control" rows="4" maxlength="2000" required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-info">{{ __('Save Note') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Reminder Modal -->
        <div class="modal fade" id="addReminderModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('abandoned-carts.storeReminder', $cart) }}">
                        @csrf
                        @honeypot
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Set Reminder') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="reminder-title" class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                                <input type="text" name="title" id="reminder-title" class="form-control" maxlength="255"
                                    placeholder="{{ __('e.g. Call the customer back') }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="reminder-message" class="form-label">{{ __('Details') }}</label>
                                <textarea name="message" id="reminder-message" class="form-control" rows="3" maxlength="2000"></textarea>
                            </div>
                            <div>
                                <label for="reminder-scheduled-at" class="form-label">{{ __('Remind At') }} <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="scheduled_at" id="reminder-scheduled-at" class="form-control"
                                    required min="{{ now()->format('Y-m-d\TH:i') }}">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-warning">{{ __('Set Reminder') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Opt-out Modal -->
        <div class="modal fade" id="optOutModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('abandoned-carts.optOut', $cart) }}">
                        @csrf
                        @honeypot
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Customer opted out') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted">
                                {{ __('The POS will stop WhatsApp messages for this cart, and the website will be told to unsubscribe the customer from its reminders.') }}
                            </p>
                            <label for="opt-out-reason" class="form-label">{{ __('Reason (optional)') }}</label>
                            <input type="text" name="reason" id="opt-out-reason" class="form-control" maxlength="500">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-dark">{{ __('Confirm opt-out') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @can('contact', $cart)
        <!-- WhatsApp Modal -->
        <div class="modal fade" id="whatsappModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('abandoned-carts.whatsapp', $cart) }}">
                        @csrf
                        @honeypot
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Send WhatsApp to') }} {{ $cart->customer_name ?? $cart->customer_phone }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">
                                {{ __('Leave the message empty to send the standard reminder with the customer\'s items and their recovery link. A custom message gets the recovery link appended.') }}
                            </p>
                            <label for="whatsapp-message" class="form-label">{{ __('Custom message (optional)') }}</label>
                            <textarea name="message" id="whatsapp-message" class="form-control" rows="4" maxlength="2000"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-success">{{ __('Send') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @can('convert', $cart)
        <!-- Convert Modal -->
        <div class="modal fade" id="convertModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('abandoned-carts.convert', $cart) }}">
                        @csrf
                        @honeypot
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Convert cart to sale') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">
                                {{ __('Sells the cart\'s items at the cart prices from the current register. VAT follows the shop\'s tax settings. The website is told the cart was recovered.') }}
                            </p>
                            <div class="mb-3">
                                <label for="convert-payment" class="form-label">{{ __('Payment method') }} <span class="text-danger">*</span></label>
                                <select name="payment_method" id="convert-payment" class="form-select" required>
                                    <option value="mobile_money">{{ __('Mobile money') }}</option>
                                    <option value="cash">{{ __('Cash') }}</option>
                                    <option value="card">{{ __('Card') }}</option>
                                    <option value="bank_transfer">{{ __('Bank transfer') }}</option>
                                </select>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_cod" value="1" id="convert-cod">
                                <label class="form-check-label" for="convert-cod">{{ __('Cash on delivery (not paid yet)') }}</label>
                            </div>
                            <div class="mb-3">
                                <label for="convert-source" class="form-label">{{ __('Sale source') }}</label>
                                <select name="source_id" id="convert-source" class="form-select">
                                    @unless ($defaultSourceId)
                                        <option value="">{{ __('Abandoned Cart') }}</option>
                                    @endunless
                                    @foreach ($saleSources as $source)
                                        <option value="{{ $source->id }}" @selected($source->id === $defaultSourceId)>{{ $source->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="convert-location" class="form-label">{{ __('Delivery location') }}</label>
                                <input type="text" name="delivery_location" id="convert-location" class="form-control" maxlength="1000">
                            </div>
                            <div class="row g-2">
                                <div class="col-6">
                                    <label for="convert-delivery-company" class="form-label">{{ __('Delivery company') }}</label>
                                    <select name="delivery_company_id" id="convert-delivery-company" class="form-select">
                                        <option value="">—</option>
                                        @foreach ($deliveryCompanies as $company)
                                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label for="convert-delivery-fee" class="form-label">{{ __('Delivery fee') }}</label>
                                    <input type="number" name="delivery_fee" id="convert-delivery-fee" class="form-control" min="0" step="0.01" value="0">
                                </div>
                            </div>
                            <div class="mt-3">
                                <label for="convert-discount" class="form-label">{{ __('Discount') }}</label>
                                <input type="number" name="discount_amount" id="convert-discount" class="form-control" min="0" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
                            <button type="submit" class="btn btn-success">{{ __('Create sale') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

@endsection

@push('scripts')
    <script>
        document.getElementById('copy-recovery-link')?.addEventListener('click', function () {
            const input = document.getElementById('recovery-link');
            const label = this.querySelector('.copy-label');
            const done = () => { label.textContent = @json(__('Copied')); setTimeout(() => label.textContent = @json(__('Copy')), 2000); };

            if (navigator.clipboard) {
                navigator.clipboard.writeText(input.value).then(done);
            } else {
                input.select();
                document.execCommand('copy');
                done();
            }
        });
    </script>
@endpush
