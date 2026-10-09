{{--
    UI Component: Modal
    
    Usage:
    <x-ui-modal id="addProductModal" title="Add New Product" size="lg">
        <x-slot:body>
            Modal content here
        </x-slot:body>
        
        <x-slot:footer>
            <x-ui-button variant="secondary" data-bs-dismiss="modal">Cancel</x-ui-button>
            <x-ui-button variant="primary" type="submit">Save</x-ui-button>
        </x-slot:footer>
    </x-ui-modal>
    
    Props:
    - id: Modal ID
    - title: Modal title
    - size: sm, lg, xl (optional)
    - centered: Center modal vertically (boolean, default false)
    - scrollable: Make modal scrollable (boolean, default false)
    - static: Disable backdrop dismiss (boolean, default false)
--}}

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true"
    @if ($static ?? false) data-bs-backdrop="static" data-bs-keyboard="false" @endif>
    <div
        class="modal-dialog 
        {{ isset($size) ? 'modal-' . $size : '' }}
        {{ $centered ?? false ? 'modal-dialog-centered' : '' }}
        {{ $scrollable ?? false ? 'modal-dialog-scrollable' : '' }}
    ">
        <div class="modal-content">
            @if (isset($title))
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $id }}Label">{{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
            @endif

            @if (isset($body))
                <div class="modal-body">
                    {{ $body }}
                </div>
            @else
                <div class="modal-body">
                    {{ $slot }}
                </div>
            @endif

            @if (isset($footer))
                <div class="modal-footer">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
