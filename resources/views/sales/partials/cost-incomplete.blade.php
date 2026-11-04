{{--
    A line, or a sale, whose cost isn't fully known (stage 10, step 6): some units came from a
    batch with no cost, so its cost and profit are estimates until the cost is entered.
--}}
@can('setCost', App\Models\Product::class)
    <a href="{{ route('products.purchase-costs.index') }}" class="badge bg-warning-subtle text-warning text-decoration-none"
        title="{{ __('Enter the purchase cost, then re-cost the sale') }}">{{ __('Cost incomplete') }}</a>
@else
    <span class="badge bg-warning-subtle text-warning">{{ __('Cost incomplete') }}</span>
@endcan
