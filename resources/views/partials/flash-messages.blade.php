{{-- Flash Messages Partial --}}

@if (session('success'))
    <x-ui-alert variant="success" dismissible icon="solar:check-circle-broken">
        {{ session('success') }}
    </x-ui-alert>
@endif

@if (session('error'))
    <x-ui-alert variant="danger" dismissible icon="solar:close-circle-broken">
        {{ session('error') }}
    </x-ui-alert>
@endif

@if (session('warning'))
    <x-ui-alert variant="warning" dismissible icon="solar:shield-warning-broken">
        {{ session('warning') }}
    </x-ui-alert>
@endif

@if (session('info'))
    <x-ui-alert variant="info" dismissible icon="solar:info-circle-broken">
        {{ session('info') }}
    </x-ui-alert>
@endif

@if ($errors->any())
    <x-ui-alert variant="danger" dismissible>
        <h6 class="alert-heading">Please fix the following errors:</h6>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-ui-alert>
@endif
