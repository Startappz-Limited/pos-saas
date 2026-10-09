<div class="main-nav">
    <!-- Sidebar Logo -->
    <div class="logo-box">
        <a href="{{ route('dashboard') }}" class="logo-dark">
            <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
            <img src="{{ asset('assets/images/logo-dark.png') }}" class="logo-lg" alt="logo dark">
        </a>

        <a href="{{ route('dashboard') }}" class="logo-light">
            <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
            <img src="{{ asset('assets/images/logo-light.png') }}" class="logo-lg" alt="logo light">
        </a>
    </div>

    <!-- Menu Toggle Button (sm-hover) -->
    <button type="button" class="button-sm-hover" aria-label="Show Full Sidebar">
        <iconify-icon icon="solar:double-alt-arrow-right-bold-duotone" class="button-sm-hover-icon"></iconify-icon>
    </button>

    <div class="scrollbar" data-simplebar>
        <ul class="navbar-nav" id="navbar-nav">

            <li class="menu-title">General</li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                    href="{{ route('dashboard') }}">
                    <span class="nav-icon">
                        <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
                    </span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            @can('shops.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('shops.*') ? 'active' : '' }}"
                        href="{{ route('shops.index') }}">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:shop-2-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Shops</span>
                    </a>
                </li>
            @endcan

            <li class="menu-title">Inventory</li>

            @can('products.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarProducts" role="button"
                        aria-expanded="{{ request()->routeIs('products.*', 'pricing-rules.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarProducts">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:t-shirt-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Products</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('products.*', 'pricing-rules.*') ? 'show' : '' }}"
                        id="sidebarProducts">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('products.index') ? 'active' : '' }}"
                                    href="{{ route('products.index') }}">List</a>
                            </li>
                            @can('products.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('products.create') ? 'active' : '' }}"
                                        href="{{ route('products.create') }}">Create</a>
                                </li>
                            @endcan
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('products.lowStock') ? 'active' : '' }}"
                                    href="{{ route('products.lowStock') }}">Low Stock</a>
                            </li>
                            @can('products.set-cost')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('products.purchase-costs.*') ? 'active' : '' }}"
                                        href="{{ route('products.purchase-costs.index') }}">Purchase Costs</a>
                                </li>
                            @endcan
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('pricing-rules.*') ? 'active' : '' }}"
                                    href="{{ route('pricing-rules.index') }}">Pricing Rules</a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endcan

            @can('categories.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarCategory" role="button"
                        aria-expanded="{{ request()->routeIs('categories.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarCategory">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:clipboard-list-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Category</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('categories.*') ? 'show' : '' }}" id="sidebarCategory">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('categories.index') ? 'active' : '' }}"
                                    href="{{ route('categories.index') }}">List</a>
                            </li>
                            @can('categories.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('categories.create') ? 'active' : '' }}"
                                        href="{{ route('categories.create') }}">Create</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcan

            @can('attributes.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarAttributes" role="button"
                        aria-expanded="{{ request()->routeIs('attributes.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarAttributes">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:confetti-minimalistic-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Attributes</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('attributes.*') ? 'show' : '' }}" id="sidebarAttributes">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('attributes.index') ? 'active' : '' }}"
                                    href="{{ route('attributes.index') }}">List</a>
                            </li>
                            @can('attributes.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('attributes.create') ? 'active' : '' }}"
                                        href="{{ route('attributes.create') }}">Create</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcan

            @can('stock.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarInventory" role="button"
                        aria-expanded="{{ request()->routeIs('stock.*', 'inventory-snapshots.*', 'stock-movements.*', 'low-stock-alerts.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarInventory">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:box-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Inventory</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('stock.*', 'inventory-snapshots.*', 'stock-movements.*', 'low-stock-alerts.*') ? 'show' : '' }}"
                        id="sidebarInventory">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('stock-intakes.*') ? 'active' : '' }}"
                                    href="{{ route('stock-intakes.index') }}">Stock Intake</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('stock-adjustments.*') ? 'active' : '' }}"
                                    href="{{ route('stock-adjustments.index') }}">Adjustments</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('stock-movements.*') ? 'active' : '' }}"
                                    href="{{ route('stock-movements.index') }}">Stock Movements</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('inventory-snapshots.*') ? 'active' : '' }}"
                                    href="{{ route('inventory-snapshots.index') }}">Snapshots</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('low-stock-alerts.*') ? 'active' : '' }}"
                                    href="{{ route('low-stock-alerts.index') }}">Low Stock Alerts</a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endcan

            @if (auth()->user()?->can('purchase_orders.view') || auth()->user()?->can('purchase_returns.view'))
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarPurchases" role="button"
                        aria-expanded="{{ request()->routeIs('purchase-orders.*', 'purchase-returns.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarPurchases">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:card-send-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Purchases</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('purchase-orders.*', 'purchase-returns.*') ? 'show' : '' }}"
                        id="sidebarPurchases">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('purchase-orders.index') ? 'active' : '' }}"
                                    href="{{ route('purchase-orders.index') }}">List</a>
                            </li>
                            @can('purchase_orders.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('purchase-orders.create') ? 'active' : '' }}"
                                        href="{{ route('purchase-orders.create') }}">Create Order</a>
                                </li>
                            @endcan
                            @can('purchase_returns.view')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('purchase-returns.*') ? 'active' : '' }}"
                                        href="{{ route('purchase-returns.index') }}">Supplier Returns</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endif

            @can('sales.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarOrders" role="button"
                        aria-expanded="{{ request()->routeIs('sales.*', 'payments.*', 'returns.*', 'refunds.*', 'ecommerce-orders.*', 'abandoned-carts.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarOrders">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:bag-smile-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Orders</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('sales.*', 'payments.*', 'returns.*', 'refunds.*', 'ecommerce-orders.*', 'abandoned-carts.*') ? 'show' : '' }}"
                        id="sidebarOrders">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('sales.index') ? 'active' : '' }}"
                                    href="{{ route('sales.index') }}">List</a>
                            </li>
                            @can('sales.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('sales.create') ? 'active' : '' }}"
                                        href="{{ route('sales.create') }}">Create</a>
                                </li>
                            @endcan
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('payments.*') ? 'active' : '' }}"
                                    href="{{ route('payments.index') }}">Payments</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('returns.*') ? 'active' : '' }}"
                                    href="{{ route('returns.index') }}">Returns</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('refunds.*') ? 'active' : '' }}"
                                    href="{{ route('refunds.index') }}">Refunds</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('ecommerce-orders.*') ? 'active' : '' }}"
                                    href="{{ route('ecommerce-orders.index') }}">Website Orders</a>
                            </li>
                            @can('viewAny', \App\Models\AbandonedCart::class)
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('abandoned-carts.*') ? 'active' : '' }}"
                                        href="{{ route('abandoned-carts.index') }}">{{ __('Abandoned Carts') }}</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcan

            @can('customers.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarCustomers" role="button"
                        aria-expanded="{{ request()->routeIs('customers.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarCustomers">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:users-group-two-rounded-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Customers</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('customers.*') ? 'show' : '' }}" id="sidebarCustomers">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('customers.index') ? 'active' : '' }}"
                                    href="{{ route('customers.index') }}">List</a>
                            </li>
                            @can('customers.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('customers.create') ? 'active' : '' }}"
                                        href="{{ route('customers.create') }}">Create</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcan

            <li class="menu-title mt-2">Finance</li>

            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('cash-registers.*') ? 'active' : '' }}"
                    href="{{ route('cash-registers.index') }}">
                    <span class="nav-icon">
                        <iconify-icon icon="solar:wallet-money-bold-duotone"></iconify-icon>
                    </span>
                    <span class="nav-text">Cash Register</span>
                </a>
            </li>

            @can('credit-sales.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarCredit" role="button"
                        aria-expanded="{{ request()->routeIs('credit-accounts.*', 'credit-transactions.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarCredit">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:card-2-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Credit Management</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('credit-accounts.*', 'credit-transactions.*') ? 'show' : '' }}"
                        id="sidebarCredit">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('credit-accounts.*') ? 'active' : '' }}"
                                    href="{{ route('credit-accounts.index') }}">Credit Accounts</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('credit-transactions.*') ? 'active' : '' }}"
                                    href="{{ route('credit-transactions.index') }}">Transactions</a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endcan

            <li class="menu-title mt-2">User Management</li>

            @can('users.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarUsers" role="button"
                        aria-expanded="{{ request()->routeIs('users.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarUsers">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:users-group-rounded-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Users</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('users.*') ? 'show' : '' }}" id="sidebarUsers">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('users.index') ? 'active' : '' }}"
                                    href="{{ route('users.index') }}">List</a>
                            </li>
                            @can('users.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('users.create') ? 'active' : '' }}"
                                        href="{{ route('users.create') }}">Create</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcan

            @can('roles.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarRoles" role="button"
                        aria-expanded="{{ request()->routeIs('roles.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarRoles">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:user-speak-rounded-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Roles</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('roles.*') ? 'show' : '' }}" id="sidebarRoles">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('roles.index') ? 'active' : '' }}"
                                    href="{{ route('roles.index') }}">List</a>
                            </li>
                            @can('roles.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('roles.create') ? 'active' : '' }}"
                                        href="{{ route('roles.create') }}">Create</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcan

            @can('customers.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarCustomers" role="button"
                        aria-expanded="{{ request()->routeIs('customers.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarCustomers">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:users-group-two-rounded-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Customers</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('customers.*') ? 'show' : '' }}" id="sidebarCustomers">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('customers.index') ? 'active' : '' }}"
                                    href="{{ route('customers.index') }}">List</a>
                            </li>
                            @can('customers.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('customers.create') ? 'active' : '' }}"
                                        href="{{ route('customers.create') }}">Create</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcan

            @can('suppliers.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarSellers" role="button"
                        aria-expanded="{{ request()->routeIs('suppliers.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarSellers">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:shop-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Sellers</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('suppliers.*') ? 'show' : '' }}" id="sidebarSellers">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('suppliers.index') ? 'active' : '' }}"
                                    href="{{ route('suppliers.index') }}">List</a>
                            </li>
                            @can('suppliers.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('suppliers.create') ? 'active' : '' }}"
                                        href="{{ route('suppliers.create') }}">Create</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcan

            <li class="menu-title mt-2">Other</li>

            @can('expenses.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarExpenses" role="button"
                        aria-expanded="{{ request()->routeIs('expenses.*', 'expense-categories.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarExpenses">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:wallet-money-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Expenses</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('expenses.*', 'expense-categories.*') ? 'show' : '' }}"
                        id="sidebarExpenses">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('expenses.index') ? 'active' : '' }}"
                                    href="{{ route('expenses.index') }}">List</a>
                            </li>
                            @can('expenses.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('expenses.create') ? 'active' : '' }}"
                                        href="{{ route('expenses.create') }}">Create</a>
                                </li>
                            @endcan
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('expense-categories.*') ? 'active' : '' }}"
                                    href="{{ route('expense-categories.index') }}">Categories</a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endcan

            @can('campaigns.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarCampaigns" role="button"
                        aria-expanded="{{ request()->routeIs('campaigns.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarCampaigns">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:leaf-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Campaigns</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('campaigns.*') ? 'show' : '' }}" id="sidebarCampaigns">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('campaigns.index') ? 'active' : '' }}"
                                    href="{{ route('campaigns.index') }}">List</a>
                            </li>
                            @can('campaigns.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('campaigns.create') ? 'active' : '' }}"
                                        href="{{ route('campaigns.create') }}">Create</a>
                                </li>
                            @endcan
                            @can('social-accounts.view')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('social-accounts.*') ? 'active' : '' }}"
                                        href="{{ route('social-accounts.index') }}">Social Accounts</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcan

            @can('baileys.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarBaileys" role="button"
                        aria-expanded="{{ request()->routeIs('baileys.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarBaileys">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:chat-round-line-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Baileys</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('baileys.*') ? 'show' : '' }}" id="sidebarBaileys">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('baileys.sessions.*') ? 'active' : '' }}"
                                    href="{{ route('baileys.sessions.index') }}">Sessions</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('baileys.inbox.*') ? 'active' : '' }}"
                                    href="{{ route('baileys.inbox.index') }}">Inbox</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('baileys.broadcast.*') ? 'active' : '' }}"
                                    href="{{ route('baileys.broadcast.show') }}">Broadcast</a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endcan

            <li class="menu-title mt-2">System</li>

            @can('users.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                        href="{{ route('users.index') }}">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:user-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Users</span>
                    </a>
                </li>
            @endcan

            @can('reports.view')
                <li class="nav-item">
                    <a class="nav-link menu-arrow" href="javascript:void(0);" data-bs-toggle="collapse"
                        data-bs-target="#sidebarReports" role="button"
                        aria-expanded="{{ request()->routeIs('reports.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarReports">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:document-text-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Reports</span>
                    </a>
                    <div class="collapse {{ request()->routeIs('reports.*') ? 'show' : '' }}" id="sidebarReports">
                        <ul class="nav sub-navbar-nav">
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('reports.index') ? 'active' : '' }}"
                                    href="{{ route('reports.index') }}">All Reports</a>
                            </li>
                            <li class="sub-nav-item">
                                <a class="sub-nav-link {{ request()->routeIs('reports.vat') ? 'active' : '' }}"
                                    href="{{ route('reports.vat') }}">{{ config('tax.label', 'VAT') }} Summary</a>
                            </li>
                        </ul>
                    </div>
                </li>
            @endcan

            @can('alerts.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('calendar.*') ? 'active' : '' }}"
                        href="{{ route('calendar.index') }}">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:calendar-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Calendar</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('alerts.*') ? 'active' : '' }}"
                        href="{{ route('alerts.index') }}">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:bell-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Alerts</span>
                    </a>
                </li>
            @endcan

            @can('audit-logs.view')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}"
                        href="{{ route('audit-logs.index') }}">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:clipboard-list-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text">Audit Logs</span>
                    </a>
                </li>
            @endcan

        </ul>
    </div>
</div>
