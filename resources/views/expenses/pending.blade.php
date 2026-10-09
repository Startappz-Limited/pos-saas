@extends('layouts.app')

@section('title', 'Pending Expenses')

@section('content')
    <div class="card">
        <div class="card-header border-bottom-dashed">
            <div class="row g-4 align-items-center">
                <div class="col-sm">
                    <div>
                        <h5 class="card-title mb-0">Pending Approval</h5>
                        <p class="text-muted mb-0">Expenses awaiting approval</p>
                    </div>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('expenses.index') }}" class="btn btn-soft-secondary">
                        <iconify-icon icon="solar:arrow-left-line-duotone" class="align-middle me-1"></iconify-icon>
                        Back to All
                    </a>
                </div>
            </div>
        </div>

        <div class="card-body">
            @if ($expenses->count() > 0)
                <div class="table-responsive table-card">
                    <table class="table table-nowrap table-striped-columns align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">Expense #</th>
                                <th scope="col">Title</th>
                                <th scope="col">Category</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Submitted By</th>
                                <th scope="col">Submitted At</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($expenses as $expense)
                                <tr>
                                    <td>
                                        <a href="{{ route('expenses.show', $expense) }}" class="fw-semibold text-primary">
                                            {{ $expense->expense_number }}
                                        </a>
                                    </td>
                                    <td>{{ Str::limit($expense->title, 30) }}</td>
                                    <td>
                                        @if ($expense->category)
                                            <span class="badge bg-light text-dark">{{ $expense->category->name }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="fw-semibold">{{ format_currency($expense->amount) }}</td>
                                    <td>{{ $expense->creator?->name ?? 'Unknown' }}</td>
                                    <td>{{ $expense->submitted_at?->format('M d, Y H:i') ?? '—' }}</td>
                                    <td>
                                        <div class="hstack gap-2">
                                            <a href="{{ route('expenses.show', $expense) }}"
                                                class="btn btn-sm btn-soft-info">
                                                <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                            </a>
                                            @can('approve', $expense)
                                                <form action="{{ route('expenses.approve', $expense) }}" method="POST"
                                                    class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-success">
                                                        <iconify-icon icon="solar:check-circle-linear"></iconify-icon>
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-end mt-3">
                    {{ $expenses->links() }}
                </div>
            @else
                <div class="noresult">
                    <div class="text-center">
                        <iconify-icon icon="solar:check-circle-bold-duotone" class="text-success"
                            style="font-size: 5rem; opacity: 0.5;"></iconify-icon>
                        <h5 class="mt-3">No Pending Expenses</h5>
                        <p class="text-muted mb-0">All expenses have been reviewed.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endsection
