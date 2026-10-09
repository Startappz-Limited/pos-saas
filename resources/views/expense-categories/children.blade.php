@extends('layouts.app')

@section('title', $expenseCategory->name . ' - Sub-Categories')

@section('content')
    <div>
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="{{ route('expense-categories.index') }}">Expense Categories</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a
                                href="{{ route('expense-categories.show', $expenseCategory) }}">{{ $expenseCategory->name }}</a>
                        </li>
                        <li class="breadcrumb-item active">Sub-Categories</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Sub-Categories of {{ $expenseCategory->name }}</h4>
            </div>
        </div>

        <div class="card">
            <div class="card-header border-bottom-dashed">
                <div class="row g-4 align-items-center">
                    <div class="col-sm">
                        <h5 class="card-title mb-0">{{ $children->count() }} Sub-Categories</h5>
                    </div>
                </div>
            </div>
            <div class="card-body">
                @if ($children->count() > 0)
                    <div class="table-responsive table-card">
                        <table class="table table-nowrap table-striped-columns align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Expenses</th>
                                    <th scope="col">Sub-Categories</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($children as $child)
                                    <tr>
                                        <td>
                                            <a href="{{ route('expense-categories.show', $child) }}"
                                                class="fw-semibold text-primary">
                                                {{ $child->name }}
                                            </a>
                                        </td>
                                        <td>{{ $child->expenses_count }}</td>
                                        <td>{{ $child->children->count() }}</td>
                                        <td>
                                            <div class="hstack gap-2">
                                                <a href="{{ route('expense-categories.show', $child) }}"
                                                    class="btn btn-sm btn-soft-info">
                                                    <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                                </a>
                                                @can('update', $child)
                                                    <a href="{{ route('expense-categories.edit', $child) }}"
                                                        class="btn btn-sm btn-soft-primary">
                                                        <iconify-icon icon="solar:pen-linear"></iconify-icon>
                                                    </a>
                                                @endcan
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4">
                        <iconify-icon icon="solar:folder-bold-duotone" class="text-muted"
                            style="font-size: 3rem; opacity: 0.5;"></iconify-icon>
                        <p class="text-muted mt-2 mb-0">No sub-categories found.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
