@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div>

        <!-- Quick Actions - Always visible -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <h5 class="mb-0 text-muted">Quick Actions</h5>
                            <div class="d-flex gap-2 flex-wrap">
                                @if (($shops ?? collect())->isNotEmpty())
                                    <form method="GET" action="{{ route('dashboard') }}" class="d-flex gap-2">
                                        <select name="shop_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="">All Accessible Shops</option>
                                            @foreach ($shops as $shop)
                                                <option value="{{ $shop->id }}" @selected(($selectedShopId ?? null) === $shop->id)>
                                                    {{ $shop->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-soft-primary">
                                            <iconify-icon icon="solar:filter-bold-duotone" class="align-middle"></iconify-icon>
                                        </button>
                                    </form>
                                @endif
                                @can('sales.create')
                                    <a href="{{ route('pos.index') }}" class="btn btn-primary">
                                        <iconify-icon icon="solar:cash-out-bold-duotone" class="me-1"></iconify-icon>
                                        New POS Sale
                                    </a>
                                @endcan
                                @can('ecommerce-orders.view')
                                    <a href="{{ route('ecommerce-orders.index') }}" class="btn btn-info position-relative">
                                        <iconify-icon icon="solar:cart-large-2-bold-duotone" class="me-1"></iconify-icon>
                                        Ecommerce Orders
                                        @if (($processingOrdersCount ?? 0) > 0)
                                            <span
                                                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                                {{ $processingOrdersCount > 99 ? '99+' : $processingOrdersCount }}
                                                <span class="visually-hidden">processing orders</span>
                                            </span>
                                        @endif
                                    </a>
                                @endcan
                                @can('sales.view')
                                    <a href="{{ route('sales.index') }}" class="btn btn-secondary">
                                        <iconify-icon icon="solar:document-text-bold-duotone" class="me-1"></iconify-icon>
                                        Sales
                                    </a>
                                @endcan
                                @can('products.view')
                                    <a href="{{ route('products.index') }}" class="btn btn-warning">
                                        <iconify-icon icon="solar:box-bold-duotone" class="me-1"></iconify-icon>
                                        Products
                                    </a>
                                @endcan
                                @can('customers.view')
                                    <a href="{{ route('customers.index') }}" class="btn btn-success">
                                        <iconify-icon icon="solar:users-group-rounded-bold-duotone"
                                            class="me-1"></iconify-icon>
                                        Customers
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @can('dashboard.analytics')
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body overflow-hidden position-relative">
                            <iconify-icon icon="solar:dollar-bold-duotone" class="fs-36 text-info"></iconify-icon>
                            <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['total_sales'] ?? 0) }}</h3>
                            <p class="text-muted">Total Sales</p>
                            @if (($statistics['sales_change'] ?? 0) >= 0)
                                <span class="badge fs-12 badge-soft-success"><i class="ti ti-arrow-badge-up"></i>
                                    {{ number_format(abs($statistics['sales_change'] ?? 0), 2) }}%</span>
                            @else
                                <span class="badge fs-12 badge-soft-danger"><i class="ti ti-arrow-badge-down"></i>
                                    {{ number_format(abs($statistics['sales_change'] ?? 0), 2) }}%</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body overflow-hidden position-relative">
                            <iconify-icon icon="solar:wallet-money-bold-duotone" class="fs-36 text-success"></iconify-icon>
                            <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['total_expenses'] ?? 0) }}</h3>
                            <p class="text-muted">Total Expenses</p>
                            @if (($statistics['expenses_change'] ?? 0) >= 0)
                                <span class="badge fs-12 badge-soft-danger"><i class="ti ti-arrow-badge-up"></i>
                                    {{ number_format(abs($statistics['expenses_change'] ?? 0), 2) }}%</span>
                            @else
                                <span class="badge fs-12 badge-soft-success"><i class="ti ti-arrow-badge-down"></i>
                                    {{ number_format(abs($statistics['expenses_change'] ?? 0), 2) }}%</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body overflow-hidden position-relative">
                            <iconify-icon icon="solar:chart-2-bold-duotone" class="fs-36 text-primary"></iconify-icon>
                            <h3 class="mb-0 fw-bold mt-3 mb-1">{{ format_currency($statistics['profit'] ?? 0) }}</h3>
                            <p class="text-muted">Net Profit</p>
                            @if (($statistics['profit_change'] ?? 0) >= 0)
                                <span class="badge fs-12 badge-soft-success"><i class="ti ti-arrow-badge-up"></i>
                                    {{ number_format(abs($statistics['profit_change'] ?? 0), 2) }}%</span>
                            @else
                                <span class="badge fs-12 badge-soft-danger"><i class="ti ti-arrow-badge-down"></i>
                                    {{ number_format(abs($statistics['profit_change'] ?? 0), 2) }}%</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-3">
                    <div class="card">
                        <div class="card-body overflow-hidden position-relative">
                            <iconify-icon icon="solar:bag-smile-bold-duotone" class="fs-36 text-warning"></iconify-icon>
                            <h3 class="mb-0 fw-bold mt-3 mb-1">{{ $statistics['total_orders'] ?? 0 }}</h3>
                            <p class="text-muted">Total Orders</p>
                            @if (($statistics['orders_change'] ?? 0) >= 0)
                                <span class="badge fs-12 badge-soft-success"><i class="ti ti-arrow-badge-up"></i>
                                    {{ number_format(abs($statistics['orders_change'] ?? 0), 2) }}%</span>
                            @else
                                <span class="badge fs-12 badge-soft-danger"><i class="ti ti-arrow-badge-down"></i>
                                    {{ number_format(abs($statistics['orders_change'] ?? 0), 2) }}%</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row">
                <div class="col-xxl-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title">Sales Overview</h4>
                            <div>
                                <button type="button" class="btn btn-sm btn-soft-secondary">ALL</button>
                                <button type="button" class="btn btn-sm btn-soft-secondary">1M</button>
                                <button type="button" class="btn btn-sm btn-soft-secondary">6M</button>
                                <button type="button" class="btn btn-sm btn-soft-secondary active">1Y</button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div dir="ltr">
                                <div id="sales-overview-chart" class="apex-charts" style="min-height: 365px;"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xxl-4">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title">Sales By Category</h4>
                            <div class="dropdown">
                                <a href="#" class="dropdown-toggle arrow-none card-drop" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                    <iconify-icon icon="iconamoon:menu-kebab-vertical-circle-duotone"
                                        class="fs-20 align-middle text-muted"></iconify-icon>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="javascript:void(0);" class="dropdown-item">View Details</a>
                                    <a href="javascript:void(0);" class="dropdown-item">Export Report</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div dir="ltr">
                                <div id="sales-category-chart" class="apex-charts mb-3"></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-nowrap table-borderless table-sm table-centered mb-0">
                                    <thead class="bg-light bg-opacity-50 thead-sm">
                                        <tr>
                                            <th>Category</th>
                                            <th>Sales</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($categoryStats ?? [] as $category)
                                            <tr>
                                                <td>{{ $category['name'] }}</td>
                                                <td>{{ format_currency($category['sales']) }}</td>
                                                <td><span
                                                        class="badge bg-primary-subtle text-primary">{{ number_format($category['percentage'], 1) }}%</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Orders & Transactions -->
            <div class="row">
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title">Recent Orders</h4>
                            <a href="{{ route('sales.index') }}" class="btn btn-sm btn-light">
                                View All
                            </a>
                        </div>
                        <div class="card-body pb-1">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 table-centered">
                                    <thead>
                                        <tr>
                                            <th class="py-1">ID</th>
                                            <th class="py-1">Date</th>
                                            <th class="py-1">Customer</th>
                                            <th class="py-1">Amount</th>
                                            <th class="py-1">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($recentOrders ?? [] as $order)
                                            <tr>
                                                <td><a href="{{ route('sales.show', $order['id']) }}"
                                                        class="text-body fw-medium">#{{ $order['order_number'] }}</a></td>
                                                <td>{{ $order['date'] }}</td>
                                                <td>{{ $order['customer_name'] }}</td>
                                                <td class="fw-medium">{{ format_currency($order['amount']) }}</td>
                                                <td>
                                                    @if ($order['status'] === 'completed')
                                                        <span class="badge bg-success-subtle text-success">Completed</span>
                                                    @elseif($order['status'] === 'pending')
                                                        <span class="badge bg-warning-subtle text-warning">Pending</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger">Cancelled</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">No recent orders</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title">Recent Payments</h4>
                            <a href="{{ route('payments.index') }}" class="btn btn-sm btn-light">
                                View All
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 table-centered">
                                    <thead>
                                        <tr>
                                            <th class="py-1">ID</th>
                                            <th class="py-1">Date</th>
                                            <th class="py-1">Amount</th>
                                            <th class="py-1">Method</th>
                                            <th class="py-1">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($recentPayments ?? [] as $payment)
                                            <tr>
                                                <td><a href="{{ route('payments.show', $payment['uuid']) }}"
                                                        class="text-body fw-medium">#{{ $payment['reference'] }}</a></td>
                                                <td>{{ $payment['date'] }}</td>
                                                <td class="fw-medium">{{ format_currency($payment['amount']) }}</td>
                                                <td>{{ $payment['method'] }}</td>
                                                <td>
                                                    @if ($payment['status'] === 'verified')
                                                        <span class="badge bg-success-subtle text-success">Verified</span>
                                                    @elseif($payment['status'] === 'pending')
                                                        <span class="badge bg-warning-subtle text-warning">Pending</span>
                                                    @else
                                                        <span class="badge bg-danger-subtle text-danger">Failed</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center text-muted py-3">No recent payments</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Low Stock & Top Products -->
            <div class="row">
                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title">Low Stock Products</h4>
                            <a href="{{ route('products.lowStock') }}" class="btn btn-sm btn-light">
                                View All
                            </a>
                        </div>
                        <div class="card-body pb-1">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 table-centered">
                                    <thead>
                                        <tr>
                                            <th class="py-1">Product</th>
                                            <th class="py-1">SKU</th>
                                            <th class="py-1">Stock</th>
                                            <th class="py-1">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($lowStockProducts ?? [] as $product)
                                            <tr>
                                                <td><a href="{{ route('products.show', $product['id']) }}"
                                                        class="text-body">{{ $product['name'] }}</a></td>
                                                <td>{{ $product['sku'] }}</td>
                                                <td class="fw-medium">{{ $product['stock_quantity'] }}</td>
                                                <td>
                                                    @if ($product['stock_quantity'] <= 0)
                                                        <span class="badge bg-danger-subtle text-danger">Out of Stock</span>
                                                    @else
                                                        <span class="badge bg-warning-subtle text-warning">Low Stock</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-3">All products have
                                                    sufficient stock</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h4 class="card-title">Top Selling Products</h4>
                            <a href="{{ route('products.index') }}" class="btn btn-sm btn-light">
                                View All
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 table-centered">
                                    <thead>
                                        <tr>
                                            <th class="py-1">Product</th>
                                            <th class="py-1">Category</th>
                                            <th class="py-1">Sales</th>
                                            <th class="py-1">Revenue</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($topProducts ?? [] as $product)
                                            <tr>
                                                <td><a href="{{ route('products.show', $product['id']) }}"
                                                        class="text-body">{{ $product['name'] }}</a></td>
                                                <td>{{ $product['category'] }}</td>
                                                <td class="fw-medium">{{ $product['sales_count'] }}</td>
                                                <td class="fw-medium text-success">
                                                    {{ format_currency($product['revenue']) }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-3">No sales data available
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endcan

    </div>
@endsection

@can('dashboard.analytics')
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Sales Overview Chart
                const salesOverviewOptions = {
                    series: [{
                        name: 'Sales',
                        data: @json($chartData['sales'] ?? [])
                    }, {
                        name: 'Expenses',
                        data: @json($chartData['expenses'] ?? [])
                    }],
                    chart: {
                        type: 'area',
                        height: 365,
                        toolbar: {
                            show: false
                        }
                    },
                    colors: ['#3762ea', '#10b759'],
                    dataLabels: {
                        enabled: false
                    },
                    stroke: {
                        curve: 'smooth',
                        width: 2
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.4,
                            opacityTo: 0.1,
                        }
                    },
                    xaxis: {
                        categories: @json($chartData['months'] ?? []),
                    },
                    yaxis: {
                        labels: {
                            formatter: function(value) {
                                return '{{ currency_symbol() }}' + value.toFixed(0);
                            }
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: function(value) {
                                return '{{ currency_symbol() }}' + value.toFixed(2);
                            }
                        }
                    }
                };

                const salesOverviewChart = new ApexCharts(document.querySelector("#sales-overview-chart"),
                    salesOverviewOptions);
                salesOverviewChart.render();

                // Sales by Category Chart
                const categoryOptions = {
                    series: @json($chartData['category_values'] ?? []),
                    chart: {
                        type: 'donut',
                        height: 250
                    },
                    labels: @json($chartData['category_labels'] ?? []),
                    colors: ['#3762ea', '#10b759', '#f7cc53', '#fa5c7c', '#6c757d'],
                    legend: {
                        show: false
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '70%'
                            }
                        }
                    }
                };

                const categoryChart = new ApexCharts(document.querySelector("#sales-category-chart"), categoryOptions);
                categoryChart.render();
            });
        </script>
    @endpush
@endcan
