@extends('layouts.platform')

@section('title', __('Role templates'))

@section('breadcrumb')
    <li class="breadcrumb-item active">{{ __('Role templates') }}</li>
@endsection

@section('content')
    <x-ui-alert variant="info">
        {{ __('Each business gets its own copy of these roles when it is created. Editing a template changes what new businesses get and what "Reset to default" applies; existing copies change only when you apply the template to all copies. The Owner role isn\'t copied: editing it changes every business\'s owners at once.') }}
    </x-ui-alert>

    <x-ui-card :title="__('Shared templates')">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Role') }}</th>
                        <th class="text-end">{{ __('Permissions') }}</th>
                        <th class="text-end">{{ __('Businesses with a copy') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($templates as $template)
                        <tr>
                            <td>
                                <span class="fw-medium">{{ $template->displayName() }}</span>
                                <span class="text-muted small">({{ $template->name }})</span>
                            </td>
                            <td class="text-end">{{ $template->permissions_count }}</td>
                            <td class="text-end">{{ $template->isOwnerRole() ? __('Not copied') : ($copies[$template->id] ?? 0) }}</td>
                            <td class="text-end">
                                <a href="{{ route('platform.roles.edit', $template) }}" class="btn btn-sm btn-soft-primary">{{ __('Edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui-card>
@endsection
