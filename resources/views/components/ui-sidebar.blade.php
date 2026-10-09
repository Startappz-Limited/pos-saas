{{--
    UI Component: Sidebar Navigation
    
    Usage: <x-ui-sidebar />
--}}

<div class="app-sidebar-menu">
    <div class="h-100" data-simplebar>

        <!--- Sidemenu -->
        <div id="sidebar-menu">

            <ul id="side-menu">

                <!-- Dashboard -->
                <li class="menu-title">Main</li>

                <li>
                    <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <i class="bx bx-home-circle"></i>
                        <span>Dashboard</span>
                    </a>
                </li>

                <!-- Shops -->
                <li>
                    <a href="{{ route('shops.index') }}" class="{{ request()->routeIs('shops.*') ? 'active' : '' }}">
                        <i class="bx bx-store"></i>
                        <span>Shops</span>
                    </a>
                </li>

                <!-- Products -->
                <li class="menu-title">Inventory</li>

                <li>
                    <a href="#sidebarProducts" data-bs-toggle="collapse"
                        class="{{ request()->routeIs('products.*', 'categories.*') ? '' : 'collapsed' }}">
                        <i class="bx bx-package"></i>
                        <span>Products</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('products.*', 'categories.*') ? 'show' : '' }}"
                        id="sidebarProducts">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('products.index') }}"
                                    class="{{ request()->routeIs('products.index') ? 'active' : '' }}">All Products</a>
                            </li>
                            <li>
                                <a href="{{ route('products.create') }}"
                                    class="{{ request()->routeIs('products.create') ? 'active' : '' }}">Add Product</a>
                            </li>
                            <li>
                                <a href="{{ route('categories.index') }}"
                                    class="{{ request()->routeIs('categories.*') ? 'active' : '' }}">Categories</a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Inventory -->
                <li>
                    <a href="#sidebarInventory" data-bs-toggle="collapse"
                        class="{{ request()->routeIs('inventory.*', 'stock-intake.*') ? '' : 'collapsed' }}">
                        <i class="bx bx-spreadsheet"></i>
                        <span>Inventory</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('inventory.*', 'stock-intake.*') ? 'show' : '' }}"
                        id="sidebarInventory">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('inventory.index') }}"
                                    class="{{ request()->routeIs('inventory.index') ? 'active' : '' }}">Stock
                                    Levels</a>
                            </li>
                            <li>
                                <a href="{{ route('stock-intake.index') }}"
                                    class="{{ request()->routeIs('stock-intake.*') ? 'active' : '' }}">Stock Intake</a>
                            </li>
                            <li>
                                <a href="{{ route('stock-adjustments.index') }}"
                                    class="{{ request()->routeIs('stock-adjustments.*') ? 'active' : '' }}">Adjustments</a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Sales -->
                <li class="menu-title">Sales</li>

                <li>
                    <a href="#sidebarSales" data-bs-toggle="collapse"
                        class="{{ request()->routeIs('sales.*', 'payments.*') ? '' : 'collapsed' }}">
                        <i class="bx bx-shopping-bag"></i>
                        <span>Sales</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('sales.*', 'payments.*') ? 'show' : '' }}"
                        id="sidebarSales">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('sales.index') }}"
                                    class="{{ request()->routeIs('sales.index') ? 'active' : '' }}">All Sales</a>
                            </li>
                            <li>
                                <a href="{{ route('sales.create') }}"
                                    class="{{ request()->routeIs('sales.create') ? 'active' : '' }}">New Sale</a>
                            </li>
                            <li>
                                <a href="{{ route('credit-sales.index') }}"
                                    class="{{ request()->routeIs('credit-sales.*') ? 'active' : '' }}">Credit Sales</a>
                            </li>
                            <li>
                                <a href="{{ route('returns.index') }}"
                                    class="{{ request()->routeIs('returns.*') ? 'active' : '' }}">Returns & Refunds</a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- Customers -->
                <li>
                    <a href="{{ route('customers.index') }}"
                        class="{{ request()->routeIs('customers.*') ? 'active' : '' }}">
                        <i class="bx bx-user-circle"></i>
                        <span>Customers</span>
                    </a>
                </li>

                <!-- Suppliers -->
                <li>
                    <a href="{{ route('suppliers.index') }}"
                        class="{{ request()->routeIs('suppliers.*') ? 'active' : '' }}">
                        <i class="bx bx-buildings"></i>
                        <span>Suppliers</span>
                    </a>
                </li>

                <!-- Financial -->
                <li class="menu-title">Financial</li>

                <li>
                    <a href="#sidebarExpenses" data-bs-toggle="collapse"
                        class="{{ request()->routeIs('expenses.*', 'expense-categories.*') ? '' : 'collapsed' }}">
                        <i class="bx bx-dollar-circle"></i>
                        <span>Expenses</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('expenses.*', 'expense-categories.*') ? 'show' : '' }}"
                        id="sidebarExpenses">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('expenses.index') }}"
                                    class="{{ request()->routeIs('expenses.index') ? 'active' : '' }}">All Expenses</a>
                            </li>
                            <li>
                                <a href="{{ route('expenses.create') }}"
                                    class="{{ request()->routeIs('expenses.create') ? 'active' : '' }}">Add Expense</a>
                            </li>
                            <li>
                                <a href="{{ route('expense-categories.index') }}"
                                    class="{{ request()->routeIs('expense-categories.*') ? 'active' : '' }}">Categories</a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li>
                    <a href="{{ route('advertising.index') }}"
                        class="{{ request()->routeIs('advertising.*') ? 'active' : '' }}">
                        <i class="bx bx-trending-up"></i>
                        <span>Advertising ROI</span>
                    </a>
                </li>

                <!-- Reports -->
                <li class="menu-title">Reports</li>

                <li>
                    <a href="#sidebarReports" data-bs-toggle="collapse"
                        class="{{ request()->routeIs('reports.*') ? '' : 'collapsed' }}">
                        <i class="bx bx-bar-chart-alt-2"></i>
                        <span>Reports</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('reports.*') ? 'show' : '' }}" id="sidebarReports">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('reports.sales') }}">Sales Report</a>
                            </li>
                            <li>
                                <a href="{{ route('reports.inventory') }}">Inventory Report</a>
                            </li>
                            <li>
                                <a href="{{ route('reports.profit-loss') }}">Profit & Loss</a>
                            </li>
                            <li>
                                <a href="{{ route('reports.expenses') }}">Expenses Report</a>
                            </li>
                        </ul>
                    </div>
                </li>

                <!-- System -->
                <li class="menu-title">System</li>

                <li>
                    <a href="{{ route('alerts.index') }}"
                        class="{{ request()->routeIs('alerts.*') ? 'active' : '' }}">
                        <i class="bx bx-bell"></i>
                        <span>Alerts</span>
                        <span class="badge bg-danger rounded-pill ms-auto">3</span>
                    </a>
                </li>

                <li>
                    <a href="#sidebarUsers" data-bs-toggle="collapse"
                        class="{{ request()->routeIs('users.*', 'roles.*') ? '' : 'collapsed' }}">
                        <i class="bx bx-group"></i>
                        <span>Users & Roles</span>
                        <span class="menu-arrow"></span>
                    </a>
                    <div class="collapse {{ request()->routeIs('users.*', 'roles.*') ? 'show' : '' }}"
                        id="sidebarUsers">
                        <ul class="nav-second-level">
                            <li>
                                <a href="{{ route('users.index') }}"
                                    class="{{ request()->routeIs('users.*') ? 'active' : '' }}">Users</a>
                            </li>
                            <li>
                                <a href="{{ route('roles.index') }}"
                                    class="{{ request()->routeIs('roles.*') ? 'active' : '' }}">Roles &
                                    Permissions</a>
                            </li>
                        </ul>
                    </div>
                </li>

                <li>
                    <a href="{{ route('audit-logs.index') }}"
                        class="{{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                        <i class="bx bx-history"></i>
                        <span>Audit Logs</span>
                    </a>
                </li>

                @can('view-api-docs')
                    <li>
                        <a href="{{ route('api-docs.index') }}"
                            class="{{ request()->routeIs('api-docs.*') ? 'active' : '' }}">
                            <i class="bx bx-code-alt"></i>
                            <span>API Documentation</span>
                        </a>
                    </li>
                @endcan

            </ul>

        </div>
        <!-- Sidebar -->

    </div>
</div>
