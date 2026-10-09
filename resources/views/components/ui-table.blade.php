{{--
    UI Component: Table
    
    Usage:
    <x-ui-table>
        <x-slot:header>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </x-slot:header>
        
        @foreach ($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->email }}</td>
            <td><x-ui-action-buttons :model="$user" route="users" /></td>
        </tr>
        @endforeach
    </x-ui-table>
    
    Props:
    - hover: Enable row hover (boolean, default true)
    - striped: Striped rows (boolean, default false)
    - bordered: Bordered table (boolean, default false)
    - class: Additional CSS classes
--}}

<div class="table-responsive">
    <table
        class="table align-middle mb-0 
        {{ $hover ?? true ? 'table-hover' : '' }} 
        {{ $striped ?? false ? 'table-striped' : '' }}
        {{ $bordered ?? false ? 'table-bordered' : '' }}
        {{ $class ?? '' }}
    ">
        @if (isset($header))
            <thead class="bg-light-subtle">
                {{ $header }}
            </thead>
        @endif

        <tbody>
            {{ $slot }}
        </tbody>

        @if (isset($footer))
            <tfoot>
                {{ $footer }}
            </tfoot>
        @endif
    </table>
</div>
