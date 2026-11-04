@extends('layouts.app')

@section('title', __('Refunds owed'))

@section('content')
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-1">{{ __('Refunds owed') }}</h4>
            <p class="text-muted mb-0">{{ __('Money voided sales took that is still owed back. Cash comes out of your own open register when you pay it; M-Pesa, card and bank refunds are made outside the system and recorded here with their reference.') }}</p>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        @foreach (['pending' => __('Owed'), 'paid' => __('Paid'), 'settled' => __('Cheques returned')] as $key => $label)
            <li class="nav-item">
                <a class="nav-link @if ($status === $key) active @endif" href="{{ route('refunds-due.index', ['status' => $key]) }}" @if ($status === $key) aria-current="page" @endif>{{ $label }}</a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Sale') }}</th>
                            <th>{{ __('Shop') }}</th>
                            <th>{{ __('Method') }}</th>
                            <th>{{ __('Reference') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            <th>{{ __('Owed since') }}</th>
                            <th>{{ $status === 'pending' ? __('Record') : __('Settled') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($refunds as $refund)
                            <tr>
                                <td>
                                    @if ($refund->sale)
                                        <a href="{{ route('sales.show', $refund->sale) }}">{{ $refund->sale->invoice_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $refund->shop?->name }}</td>
                                <td><span class="badge bg-info-subtle text-info">{{ $refund->methodLabel() }}</span></td>
                                <td>{{ $refund->reference ?? '—' }}</td>
                                <td class="text-end">{{ format_currency($refund->amount) }}</td>
                                <td>{{ $refund->created_at?->diffForHumans() }}</td>
                                <td>
                                    @if ($refund->isPending())
                                        @can('pay', $refund)
                                            <form action="{{ route('refunds-due.pay', $refund) }}" method="POST" class="d-flex flex-wrap gap-2">
                                                @csrf
                                                <input type="hidden" name="resolution" value="paid">
                                                <input type="hidden" name="method" value="{{ $refund->method }}">
                                                @if ($refund->method !== 'cash')
                                                    <label for="refund-reference-{{ $refund->id }}" class="visually-hidden">{{ __('Refund reference') }}</label>
                                                    <input type="text" name="reference" id="refund-reference-{{ $refund->id }}" class="form-control form-control-sm" maxlength="255" required placeholder="{{ __('Refund reference') }}">
                                                @endif
                                                <button type="submit" class="btn btn-sm btn-success" @if ($refund->method === 'cash' && ! $ownSessionShops($refund->shop_id)) disabled aria-describedby="refund-cash-help-{{ $refund->id }}" @endif>{{ __('Mark paid') }}</button>
                                                @if ($refund->method === 'cash' && ! $ownSessionShops($refund->shop_id))
                                                    <small class="text-muted d-block w-100" id="refund-cash-help-{{ $refund->id }}">{{ __('Open your register in this shop to pay it in cash.') }}</small>
                                                @endif
                                            </form>
                                            @if ($refund->method === 'cheque')
                                                <form action="{{ route('refunds-due.pay', $refund) }}" method="POST" class="mt-2">
                                                    @csrf
                                                    <input type="hidden" name="resolution" value="cheque_returned">
                                                    <button type="submit" class="btn btn-sm btn-light">{{ __('Cheque returned') }}</button>
                                                </form>
                                            @endif
                                        @else
                                            <span class="text-muted">{{ __('Owed') }}</span>
                                        @endcan
                                    @else
                                        {{ $refund->resolution === 'cheque_returned' ? __('Cheque returned') : __('Paid') }}
                                        {{ __('by :name, :when', ['name' => $refund->resolver?->name ?? '—', 'when' => $refund->resolved_at?->format('M d, Y H:i')]) }}
                                        @if ($refund->resolved_reference)
                                            <br><small class="text-muted">{{ $refund->resolved_reference }}</small>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ __('Nothing here.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="mt-3">{{ $refunds->links() }}</div>
@endsection
