@extends('layouts.app')

@section('title', $expenseCategory->name . ' - Hierarchy')

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
                        <li class="breadcrumb-item active">Hierarchy</li>
                    </ol>
                </nav>
                <h4 class="mb-0">Category Hierarchy</h4>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-8">
                <!-- Ancestor Path -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Ancestor Path</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            @foreach ($hierarchy as $ancestor)
                                <a href="{{ route('expense-categories.show', $ancestor) }}"
                                    class="badge bg-{{ $ancestor->id === $expenseCategory->id ? 'primary' : 'light text-dark' }} fs-6 px-3 py-2">
                                    {{ $ancestor->name }}
                                </a>
                                @if (!$loop->last)
                                    <iconify-icon icon="solar:arrow-right-linear" class="text-muted"></iconify-icon>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Descendants -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            Descendants ({{ $descendants->count() }})
                        </h5>
                    </div>
                    <div class="card-body">
                        @if ($descendants->count() > 0)
                            <div class="table-responsive">
                                <table class="table table-nowrap align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Depth</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($descendants as $descendant)
                                            <tr>
                                                <td>
                                                    <span
                                                        style="padding-left: {{ ($descendant->depth - $expenseCategory->depth - 1) * 20 }}px">
                                                        @if ($descendant->children->count() > 0)
                                                            <iconify-icon icon="solar:folder-bold-duotone"
                                                                class="text-warning align-middle me-1"></iconify-icon>
                                                        @else
                                                            <iconify-icon icon="solar:document-bold-duotone"
                                                                class="text-muted align-middle me-1"></iconify-icon>
                                                        @endif
                                                        <a href="{{ route('expense-categories.show', $descendant) }}">
                                                            {{ $descendant->name }}
                                                        </a>
                                                    </span>
                                                </td>
                                                <td>{{ $descendant->depth }}</td>
                                                <td>
                                                    <a href="{{ route('expense-categories.show', $descendant) }}"
                                                        class="btn btn-sm btn-soft-info">
                                                        <iconify-icon icon="solar:eye-linear"></iconify-icon>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-muted text-center mb-0">No descendants found.</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <!-- Current Category Info -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Current Category</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <p class="text-muted mb-1">Name</p>
                            <p class="mb-0 fw-semibold">{{ $expenseCategory->name }}</p>
                        </div>
                        <div class="mb-3">
                            <p class="text-muted mb-1">Type</p>
                            <span
                                class="badge bg-{{ $expenseCategory->type->color() }}-subtle text-{{ $expenseCategory->type->color() }}">
                                {{ $expenseCategory->type->label() }}
                            </span>
                        </div>
                        <div class="mb-3">
                            <p class="text-muted mb-1">Depth</p>
                            <p class="mb-0">{{ $expenseCategory->depth ?? 0 }}</p>
                        </div>
                        <div>
                            <p class="text-muted mb-1">Direct Children</p>
                            <p class="mb-0">{{ $expenseCategory->children->count() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
