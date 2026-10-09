{{--
    UI Component: Card
    
    Usage:
    <x-ui-card title="Card Title">
        Card content here
    </x-ui-card>
    
    With header actions:
    <x-ui-card title="Products">
        <x-slot:headerActions>
            <a href="#" class="btn btn-sm btn-primary">Add New</a>
        </x-slot:headerActions>
        
        Card content
    </x-ui-card>
    
    Props:
    - title: Card header title (optional)
    - footer: Show footer (boolean, default false)
    - class: Additional CSS classes
    - headerClass: Header CSS classes
    - bodyClass: Body CSS classes
--}}

<div class="card {{ $class ?? '' }}">
    @if (isset($title) || isset($headerActions))
        <div
            class="card-header {{ $headerClass ?? '' }} @if (isset($headerActions)) d-flex justify-content-between align-items-center @endif">
            @if (isset($title))
                <h4 class="card-title mb-0 {{ isset($headerActions) ? 'flex-grow-1' : '' }}">{{ $title }}</h4>
            @endif

            @if (isset($headerActions))
                <div class="d-flex align-items-center gap-2">
                    {{ $headerActions }}
                </div>
            @endif
        </div>
    @endif

    <div class="card-body {{ $bodyClass ?? '' }}">
        {{ $slot }}
    </div>

    @if (($footer ?? false) && isset($footerContent))
        <div class="card-footer {{ $footerClass ?? '' }}">
            {{ $footerContent }}
        </div>
    @endif
</div>
