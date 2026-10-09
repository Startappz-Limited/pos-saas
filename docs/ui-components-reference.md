# UI Components Quick Reference
## Stock Taking & Sales Management System

This file provides quick examples of all available UI components.

---

## Layout Components

### Main Layout
```blade
<x-ui-layout title="Dashboard" :breadcrumbs="['Home', 'Dashboard']">
    Page content here
</x-ui-layout>
```

### Breadcrumb
```blade
<x-ui-breadcrumb :items="[
    ['label' => 'Home', 'url' => route('dashboard')],
    ['label' => 'Products', 'url' => route('products.index')],
    'Edit'
]" />
```

---

## Card Components

### Basic Card
```blade
<x-ui-card title="Card Title">
    Card content
</x-ui-card>
```

### Card with Header Actions
```blade
<x-ui-card title="Products">
    <x-slot:headerActions>
        <x-ui-button variant="primary" icon="solar:add-circle-broken" href="{{ route('products.create') }}">
            Add Product
        </x-ui-button>
    </x-slot:headerActions>
    
    Table or content here
</x-ui-card>
```

### Stats Card
```blade
<x-ui-card-stats 
    title="Total Sales" 
    value="$13,647" 
    icon="solar:cart-5-bold-duotone"
    trend="+12.5%"
    trendDirection="up"
    trendLabel="Last Month"
    link="{{ route('sales.index') }}"
/>
```

---

## Table Components

### Data Table
```blade
<x-ui-table>
    <x-slot:header>
        <tr>
            <th>Product Name</th>
            <th>SKU</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Actions</th>
        </tr>
    </x-slot:header>
    
    @forelse($products as $product)
    <tr>
        <td>{{ $product->name }}</td>
        <td>{{ $product->sku }}</td>
        <td>${{ number_format($product->price, 2) }}</td>
        <td>{{ $product->stock_quantity }}</td>
        <td><x-ui-action-buttons :model="$product" route="products" /></td>
    </tr>
    @empty
    <tr>
        <td colspan="5">
            <x-ui-empty-state 
                icon="solar:box-broken"
                title="No products found"
                actionText="Add Product"
                actionUrl="{{ route('products.create') }}"
            />
        </td>
    </tr>
    @endforelse
</x-ui-table>
```

---

## Form Components

### Input Field
```blade
<x-ui-form-input 
    name="product_name" 
    label="Product Name" 
    value="{{ old('product_name', $product->name ?? '') }}"
    placeholder="Enter product name"
    required
    help="Provide a unique, descriptive name"
/>
```

### Select Dropdown
```blade
<x-ui-form-select 
    name="category_id" 
    label="Category"
    :options="$categories->pluck('name', 'id')"
    value="{{ old('category_id', $product->category_id ?? '') }}"
    placeholder="Select a category"
    required
/>
```

---

## Button Components

### Basic Buttons
```blade
<x-ui-button variant="primary" icon="solar:add-circle-broken">
    Add New
</x-ui-button>

<x-ui-button variant="danger" size="sm" type="submit">
    Delete
</x-ui-button>

<x-ui-button variant="outline-secondary" icon="solar:download-minimalistic-broken" iconPosition="right">
    Export
</x-ui-button>
```

### Action Buttons
```blade
<x-ui-action-buttons 
    :model="$product" 
    route="products"
    :showView="true"
    :showEdit="true"
    :showDelete="true"
/>
```

---

## Badge Components

```blade
<x-ui-badge variant="success">Active</x-ui-badge>
<x-ui-badge variant="danger" pill>Inactive</x-ui-badge>
<x-ui-badge variant="warning" soft>Pending</x-ui-badge>
```

---

## Alert Components

```blade
<x-ui-alert variant="success" dismissible icon="solar:check-circle-broken">
    Product created successfully!
</x-ui-alert>

<x-ui-alert variant="danger" icon="solar:danger-circle-broken">
    An error occurred while processing your request.
</x-ui-alert>
```

---

## Modal Components

```blade
<x-ui-modal id="confirmDeleteModal" title="Confirm Delete" centered>
    <x-slot:body>
        Are you sure you want to delete this product?
    </x-slot:body>
    
    <x-slot:footer>
        <x-ui-button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui-button>
        <x-ui-button variant="danger" type="submit">Delete</x-ui-button>
    </x-slot:footer>
</x-ui-modal>

<!-- Trigger -->
<x-ui-button variant="danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
    Delete
</x-ui-button>
```

---

## Loading & Empty States

### Loading Spinner
```blade
<x-ui-loading text="Loading products..." />
<x-ui-loading size="sm" variant="primary" />
```

### Empty State
```blade
<x-ui-empty-state 
    icon="solar:box-broken"
    title="No products found"
    message="Get started by adding your first product"
    actionText="Add Product"
    actionUrl="{{ route('products.create') }}"
/>
```

---

## Complete Examples

### Product List Page
```blade
<x-ui-layout title="Products" :breadcrumbs="['Home', 'Products']">
    <div class="row">
        <div class="col-12">
            <x-ui-card title="All Products">
                <x-slot:headerActions>
                    <x-ui-button variant="primary" icon="solar:add-circle-broken" href="{{ route('products.create') }}">
                        Add Product
                    </x-ui-button>
                </x-slot:headerActions>
                
                <x-ui-table>
                    <x-slot:header>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </x-slot:header>
                    
                    @foreach($products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->sku }}</td>
                        <td>{{ $product->category->name }}</td>
                        <td>${{ number_format($product->price, 2) }}</td>
                        <td>{{ $product->stock_quantity }}</td>
                        <td>
                            <x-ui-badge :variant="$product->is_active ? 'success' : 'danger'">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </x-ui-badge>
                        </td>
                        <td><x-ui-action-buttons :model="$product" route="products" /></td>
                    </tr>
                    @endforeach
                </x-ui-table>
                
                <div class="mt-3">
                    {{ $products->links() }}
                </div>
            </x-ui-card>
        </div>
    </div>
</x-ui-layout>
```

### Product Create/Edit Form
```blade
<x-ui-layout title="Add Product" :breadcrumbs="['Home', 'Products', 'Add']">
    <div class="row">
        <div class="col-xl-8 col-lg-10 mx-auto">
            <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <x-ui-card title="Product Information">
                    <div class="row">
                        <div class="col-lg-6">
                            <x-ui-form-input 
                                name="name" 
                                label="Product Name" 
                                placeholder="Enter product name"
                                required
                            />
                        </div>
                        
                        <div class="col-lg-6">
                            <x-ui-form-input 
                                name="sku" 
                                label="SKU" 
                                placeholder="Auto-generated"
                            />
                        </div>
                        
                        <div class="col-lg-6">
                            <x-ui-form-select 
                                name="category_id" 
                                label="Category"
                                :options="$categories->pluck('name', 'id')"
                                placeholder="Select category"
                                required
                            />
                        </div>
                        
                        <div class="col-lg-6">
                            <x-ui-form-input 
                                name="price" 
                                type="number" 
                                label="Price" 
                                placeholder="0.00"
                                step="0.01"
                                required
                            />
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <x-ui-button variant="primary" type="submit" icon="solar:check-circle-broken">
                            Save Product
                        </x-ui-button>
                        <x-ui-button variant="secondary" href="{{ route('products.index') }}">
                            Cancel
                        </x-ui-button>
                    </div>
                </x-ui-card>
            </form>
        </div>
    </div>
</x-ui-layout>
```

### Dashboard with Stats
```blade
<x-ui-layout title="Dashboard">
    <div class="row">
        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats 
                title="Total Sales" 
                value="$13,647" 
                icon="solar:cart-5-bold-duotone"
                trend="+12.5%"
                trendDirection="up"
                trendLabel="Last Month"
            />
        </div>
        
        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats 
                title="Products" 
                value="1,256" 
                icon="solar:box-bold-duotone"
                trend="+5.2%"
                trendDirection="up"
                trendLabel="This Week"
            />
        </div>
        
        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats 
                title="Customers" 
                value="854" 
                icon="solar:users-group-rounded-bold-duotone"
                trend="-2.1%"
                trendDirection="down"
                trendLabel="Last Week"
            />
        </div>
        
        <div class="col-md-6 col-xl-3">
            <x-ui-card-stats 
                title="Revenue" 
                value="$45,280" 
                icon="solar:dollar-minimalistic-bold-duotone"
                trend="+8.7%"
                trendDirection="up"
                trendLabel="Last Month"
            />
        </div>
    </div>
</x-ui-layout>
```

---

## Flash Messages

Flash messages are automatically displayed via `partials/flash-messages.blade.php` included in the layout.

### Setting Flash Messages in Controllers
```php
return redirect()->route('products.index')
    ->with('success', 'Product created successfully!');

return redirect()->back()
    ->with('error', 'Failed to delete product.');

return redirect()->route('sales.index')
    ->with('warning', 'Stock is running low.');

return redirect()->back()
    ->with('info', 'Your report is being generated.');
```

---

## Best Practices

1. **Always use components** instead of writing raw HTML
2. **Consistent spacing**: Use Bootstrap's mb-3 for form groups
3. **Accessibility**: Include proper labels, ARIA attributes
4. **Validation**: Always show validation errors
5. **Responsive**: Test on mobile devices
6. **Icons**: Use Iconify icons for consistency
7. **Colors**: Use Bootstrap variants (primary, success, danger, etc.)
8. **Loading states**: Show spinners during async operations
9. **Empty states**: Always handle empty data gracefully
10. **Permissions**: Use @can directives for authorization

---

**Created:** February 4, 2026  
**Components:** 16+ reusable UI components  
**Status:** Ready for implementation
