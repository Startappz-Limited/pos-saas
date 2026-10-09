{{--
    UI Component: Stats Card
    
    Usage:
    <x-ui-card-stats 
        title="Total Orders" 
        value="13,647" 
        icon="solar:cart-5-bold-duotone"
        trend="+2.3%"
        trendDirection="up"
        trendLabel="Last Week"
        link="#"
    />
    
    Props:
    - title: Card title
    - value: Main stat value
    - icon: Iconify icon name
    - trend: Trend percentage (optional)
    - trendDirection: 'up' or 'down' (default 'up')
    - trendLabel: Trend description (optional)
    - link: View more link (optional)
    - class: Additional CSS classes
--}}

<div class="card overflow-hidden {{ $class ?? '' }}">
    <div class="card-body">
        <div class="row">
            <div class="col-6">
                <div class="avatar-md bg-soft-primary rounded">
                    @if (isset($icon))
                        <iconify-icon icon="{{ $icon }}" class="avatar-title fs-32 text-primary"></iconify-icon>
                    @else
                        <i class="bx bx-trending-up avatar-title fs-24 text-primary"></i>
                    @endif
                </div>
            </div>
            <div class="col-6 text-end">
                <p class="text-muted mb-0 text-truncate">{{ $title }}</p>
                <h3 class="text-dark mt-1 mb-0">{{ $value }}</h3>
            </div>
        </div>
    </div>

    @if (isset($trend) || isset($link))
        <div class="card-footer py-2 bg-light bg-opacity-50">
            <div class="d-flex align-items-center justify-content-between">
                @if (isset($trend))
                    <div>
                        <span class="text-{{ ($trendDirection ?? 'up') === 'up' ? 'success' : 'danger' }}">
                            <i class="bx bxs-{{ ($trendDirection ?? 'up') === 'up' ? 'up' : 'down' }}-arrow fs-12"></i>
                            {{ $trend }}
                        </span>
                        @if (isset($trendLabel))
                            <span class="text-muted ms-1 fs-12">{{ $trendLabel }}</span>
                        @endif
                    </div>
                @endif

                @if (isset($link))
                    <a href="{{ $link }}" class="text-reset fw-semibold fs-12">View More</a>
                @endif
            </div>
        </div>
    @endif
</div>
