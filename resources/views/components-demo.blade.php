@extends('layouts.app')

@section('title', 'UI Components Demo')

@section('content')
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="mb-3">Stats Cards</h3>
        </div>
        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats title="Total Sales" value="$13,647" icon="solar:cart-5-bold-duotone" trend="+12.5%"
                trendDirection="up" trendLabel="Last Month" link="#" />
        </div>
        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats title="Products" value="1,256" icon="solar:box-bold-duotone" trend="+5.2%"
                trendDirection="up" trendLabel="This Week" />
        </div>
        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats title="Customers" value="854" icon="solar:users-group-rounded-bold-duotone" trend="-2.1%"
                trendDirection="down" trendLabel="Last Week" />
        </div>
        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats title="Revenue" value="$45,280" icon="solar:dollar-minimalistic-bold-duotone" trend="+8.7%"
                trendDirection="up" trendLabel="Last Month" />
        </div>
    </div>

    <!-- Cards -->
    <div class="row mb-4">
        <div class="col-12">
            <h3 class="mb-3">Cards</h3>
        </div>
        <div class="col-md-6">
            <x-ui-card title="Basic Card">
                This is a basic card with a title and content.
            </x-ui-card>
        </div>
        <div class="col-md-6">
            <x-ui-card title="Card with Actions">
                <x-slot:headerActions>
                    <x-ui-button variant="primary" size="sm" icon="solar:add-circle-broken">
                        Add New
                    </x-ui-button>
                </x-slot:headerActions>

                This card has header actions (buttons in the header).
            </x-ui-card>
        </div>
    </div>

    <!-- Buttons -->
    <div class="row mb-4">
        <div class="col-12">
            <x-ui-card title="Buttons">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <x-ui-button variant="primary">Primary</x-ui-button>
                    <x-ui-button variant="secondary">Secondary</x-ui-button>
                    <x-ui-button variant="success">Success</x-ui-button>
                    <x-ui-button variant="danger">Danger</x-ui-button>
                    <x-ui-button variant="warning">Warning</x-ui-button>
                    <x-ui-button variant="info">Info</x-ui-button>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <x-ui-button variant="primary" size="sm">Small</x-ui-button>
                    <x-ui-button variant="primary">Normal</x-ui-button>
                    <x-ui-button variant="primary" size="lg">Large</x-ui-button>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <x-ui-button variant="primary" icon="solar:add-circle-broken">With Icon</x-ui-button>
                    <x-ui-button variant="success" icon="solar:check-circle-broken" iconPosition="right">Icon
                        Right</x-ui-button>
                    <x-ui-button variant="outline-primary">Outline</x-ui-button>
                </div>
            </x-ui-card>
        </div>
    </div>

    <!-- Badges -->
    <div class="row mb-4">
        <div class="col-12">
            <x-ui-card title="Badges">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <x-ui-badge variant="primary">Primary</x-ui-badge>
                    <x-ui-badge variant="secondary">Secondary</x-ui-badge>
                    <x-ui-badge variant="success">Success</x-ui-badge>
                    <x-ui-badge variant="danger">Danger</x-ui-badge>
                    <x-ui-badge variant="warning">Warning</x-ui-badge>
                    <x-ui-badge variant="info">Info</x-ui-badge>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <x-ui-badge variant="primary" pill>Pill Primary</x-ui-badge>
                    <x-ui-badge variant="success" pill>Pill Success</x-ui-badge>
                    <x-ui-badge variant="danger" pill>Pill Danger</x-ui-badge>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <x-ui-badge variant="primary" soft>Soft Primary</x-ui-badge>
                    <x-ui-badge variant="success" soft>Soft Success</x-ui-badge>
                    <x-ui-badge variant="danger" soft>Soft Danger</x-ui-badge>
                </div>
            </x-ui-card>
        </div>
    </div>

    <!-- Alerts -->
    <div class="row mb-4">
        <div class="col-12">
            <x-ui-card title="Alerts">
                <x-ui-alert variant="success" dismissible icon="solar:check-circle-broken">
                    This is a success alert with an icon and dismiss button.
                </x-ui-alert>

                <x-ui-alert variant="danger" icon="solar:close-circle-broken">
                    This is a danger alert with an icon.
                </x-ui-alert>

                <x-ui-alert variant="warning" dismissible>
                    This is a warning alert with a dismiss button.
                </x-ui-alert>

                <x-ui-alert variant="info">
                    This is an info alert.
                </x-ui-alert>
            </x-ui-card>
        </div>
    </div>

    <!-- Forms -->
    <div class="row mb-4">
        <div class="col-12">
            <x-ui-card title="Form Components">
                <form>
                    <div class="row">
                        <div class="col-md-6">
                            <x-ui-form-input name="demo_name" label="Full Name" placeholder="Enter your name" required
                                help="This is a help text below the input" />
                        </div>

                        <div class="col-md-6">
                            <x-ui-form-input name="demo_email" type="email" label="Email Address"
                                placeholder="example@domain.com" required />
                        </div>

                        <div class="col-md-6">
                            <x-ui-form-select name="demo_category" label="Category" placeholder="Select a category"
                                required>
                                <option value="1">Category 1</option>
                                <option value="2">Category 2</option>
                                <option value="3">Category 3</option>
                            </x-ui-form-select>
                        </div>

                        <div class="col-md-6">
                            <x-ui-form-input name="demo_price" type="number" label="Price" placeholder="0.00"
                                step="0.01" />
                        </div>
                    </div>

                    <div class="mt-3">
                        <x-ui-button variant="primary" type="submit" icon="solar:check-circle-broken">
                            Submit Form
                        </x-ui-button>
                        <x-ui-button variant="secondary" type="reset">
                            Reset
                        </x-ui-button>
                    </div>
                </form>
            </x-ui-card>
        </div>
    </div>

    <!-- Table -->
    <div class="row mb-4">
        <div class="col-12">
            <x-ui-card title="Data Table">
                <x-slot:headerActions>
                    <x-ui-button variant="primary" size="sm" icon="solar:add-circle-broken">
                        Add New
                    </x-ui-button>
                </x-slot:headerActions>

                <x-ui-table>
                    <x-slot:header>
                        <tr>
                            <th>Product Name</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </x-slot:header>

                    <tr>
                        <td>Premium Protein Powder</td>
                        <td>PRO-001</td>
                        <td>$49.99</td>
                        <td>125</td>
                        <td><x-ui-badge variant="success">Active</x-ui-badge></td>
                        <td>
                            <div class="d-flex gap-2">
                                <x-ui-button variant="light" size="sm">
                                    <iconify-icon icon="solar:eye-broken" class="align-middle fs-18"></iconify-icon>
                                </x-ui-button>
                                <x-ui-button variant="soft-primary" size="sm">
                                    <iconify-icon icon="solar:pen-2-broken" class="align-middle fs-18"></iconify-icon>
                                </x-ui-button>
                                <x-ui-button variant="soft-danger" size="sm">
                                    <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                        class="align-middle fs-18"></iconify-icon>
                                </x-ui-button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>Energy Bar Pack</td>
                        <td>ENR-002</td>
                        <td>$24.99</td>
                        <td>85</td>
                        <td><x-ui-badge variant="success">Active</x-ui-badge></td>
                        <td>
                            <div class="d-flex gap-2">
                                <x-ui-button variant="light" size="sm">
                                    <iconify-icon icon="solar:eye-broken" class="align-middle fs-18"></iconify-icon>
                                </x-ui-button>
                                <x-ui-button variant="soft-primary" size="sm">
                                    <iconify-icon icon="solar:pen-2-broken" class="align-middle fs-18"></iconify-icon>
                                </x-ui-button>
                                <x-ui-button variant="soft-danger" size="sm">
                                    <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                        class="align-middle fs-18"></iconify-icon>
                                </x-ui-button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>Vitamin C Tablets</td>
                        <td>VIT-003</td>
                        <td>$15.99</td>
                        <td>5</td>
                        <td><x-ui-badge variant="warning">Low Stock</x-ui-badge></td>
                        <td>
                            <div class="d-flex gap-2">
                                <x-ui-button variant="light" size="sm">
                                    <iconify-icon icon="solar:eye-broken" class="align-middle fs-18"></iconify-icon>
                                </x-ui-button>
                                <x-ui-button variant="soft-primary" size="sm">
                                    <iconify-icon icon="solar:pen-2-broken" class="align-middle fs-18"></iconify-icon>
                                </x-ui-button>
                                <x-ui-button variant="soft-danger" size="sm">
                                    <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                        class="align-middle fs-18"></iconify-icon>
                                </x-ui-button>
                            </div>
                        </td>
                    </tr>
                </x-ui-table>
            </x-ui-card>
        </div>
    </div>

    <!-- Loading States -->
    <div class="row mb-4">
        <div class="col-md-6">
            <x-ui-card title="Loading States">
                <x-ui-loading text="Loading data..." />
            </x-ui-card>
        </div>

        <div class="col-md-6">
            <x-ui-card title="Empty State">
                <x-ui-empty-state icon="solar:box-broken" title="No items found"
                    message="Get started by adding your first item" actionText="Add Item" actionUrl="#"
                    actionIcon="solar:add-circle-broken" />
            </x-ui-card>
        </div>
    </div>

    <!-- Modal Example -->
    <div class="row mb-4">
        <div class="col-12">
            <x-ui-card title="Modal Example">
                <p>Click the button below to open a modal dialog:</p>

                <x-ui-button variant="primary" data-bs-toggle="modal" data-bs-target="#demoModal">
                    Open Modal
                </x-ui-button>
            </x-ui-card>
        </div>
    </div>

    @push('modals')
        <!-- Demo Modal -->
        <x-ui-modal id="demoModal" title="Demo Modal" centered>
            <x-slot:body>
                <p>This is a demo modal dialog. It can contain any content including forms, tables, or images.</p>

                <x-ui-form-input name="modal_input" label="Sample Input" placeholder="Enter something..." />
            </x-slot:body>

            <x-slot:footer>
                <x-ui-button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui-button>
                <x-ui-button variant="primary" type="submit">Save Changes</x-ui-button>
            </x-slot:footer>
        </x-ui-modal>
    @endpush
@endsection
