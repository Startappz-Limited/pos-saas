{{--
    UI Component: Breadcrumb
    
    Usage:
    <x-ui-breadcrumb :items="['Home', 'Products', 'Edit']" />
    
    Or with links:
    <x-ui-breadcrumb :items="[
        ['label' => 'Home', 'url' => route('dashboard')],
        ['label' => 'Products', 'url' => route('products.index')],
        'Edit'
    ]" />
    
    Props:
    - items: Array of breadcrumb items (strings or arrays)
--}}

@if (isset($items) && count($items) > 0)
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">{{ is_array(end($items)) ? end($items)['label'] : end($items) }}</h4>

                <div class="page-title-right">
                    <ol class="breadcrumb m-0">
                        @foreach ($items as $index => $item)
                            @if ($loop->last)
                                <li class="breadcrumb-item active">
                                    {{ is_array($item) ? $item['label'] : $item }}
                                </li>
                            @else
                                <li class="breadcrumb-item">
                                    @if (is_array($item) && isset($item['url']))
                                        <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                                    @else
                                        {{ is_array($item) ? $item['label'] : $item }}
                                    @endif
                                </li>
                            @endif
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </div>
@endif
