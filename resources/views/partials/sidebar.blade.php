<div class="main-nav">
    <!-- Sidebar Logo -->
    <div class="logo-box">
        <a href="{{ route('dashboard') }}" class="logo-dark">
            <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="{{ config('app.name') }}">
            <img src="{{ asset('assets/images/logo-dark.png') }}" class="logo-lg" alt="{{ config('app.name') }}">
        </a>

        <a href="{{ route('dashboard') }}" class="logo-light">
            <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="{{ config('app.name') }}">
            <img src="{{ asset('assets/images/logo-light.png') }}" class="logo-lg" alt="{{ config('app.name') }}">
        </a>
    </div>

    <!-- Menu Toggle Button (sm-hover) -->
    <button type="button" class="button-sm-hover" aria-label="{{ __('Show the full menu') }}">
        <iconify-icon icon="solar:double-alt-arrow-right-bold-duotone" class="button-sm-hover-icon" aria-hidden="true"></iconify-icon>
    </button>

    <div class="scrollbar" data-simplebar>
        {{-- One definition (App\Support\Navigation\Sidebar), filtered for the user: a link only when its page lets them in, a section only when it has one. --}}
        <ul class="navbar-nav" id="navbar-nav">
            @foreach (\App\Support\Navigation\Sidebar::for(auth()->user()) as $section)
                <li class="menu-title {{ $loop->first ? '' : 'mt-2' }}">{{ $section['title'] }}</li>

                @foreach ($section['items'] as $item)
                    @if (isset($item['children']))
                        <li class="nav-item">
                            <a class="nav-link menu-arrow {{ $item['active'] ? 'active' : '' }}" href="#sidebar-{{ $item['key'] }}"
                                data-bs-toggle="collapse" role="button" aria-expanded="{{ $item['active'] ? 'true' : 'false' }}"
                                aria-controls="sidebar-{{ $item['key'] }}">
                                <span class="nav-icon"><iconify-icon icon="{{ $item['icon'] }}" aria-hidden="true"></iconify-icon></span>
                                <span class="nav-text">{{ $item['label'] }}</span>
                            </a>
                            <div class="collapse {{ $item['active'] ? 'show' : '' }}" id="sidebar-{{ $item['key'] }}">
                                <ul class="nav sub-navbar-nav">
                                    @foreach ($item['children'] as $child)
                                        @php($current = request()->routeIs(...$child['active']))
                                        <li class="sub-nav-item">
                                            <a class="sub-nav-link {{ $current ? 'active' : '' }}" href="{{ route($child['route']) }}"
                                                @if ($current) aria-current="page" @endif>{{ $child['label'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link {{ $item['active'] ? 'active' : '' }}" href="{{ route($item['route']) }}"
                                @if ($item['active']) aria-current="page" @endif>
                                <span class="nav-icon"><iconify-icon icon="{{ $item['icon'] }}" aria-hidden="true"></iconify-icon></span>
                                <span class="nav-text">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            @endforeach
        </ul>
    </div>
</div>
