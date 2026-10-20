@extends('layouts.app')

@section('title', 'Messaging accounts')

@section('content')
    @php
        $titles = ['sms' => __('SMS'), 'whatsapp_cloud' => __('WhatsApp Cloud API')];
        $labels = [
            'api_url' => __('API URL (https)'), 'api_token' => __('API token'),
            'sender_id' => __('Sender ID'), 'phone_number_id' => __('Phone number ID'),
        ];
    @endphp

    <div class="row">
        @foreach ($fields as $type => $typeFields)
            @php($integration = $saved->get($type))
            <div class="col-lg-6">
                <x-ui-card title="{{ $titles[$type] }}">
                    <p class="text-muted">
                        {{ __('Messages for this business go out through your own account. The details are stored encrypted and never shown again.') }}
                    </p>

                    @if ($integration)
                        <div class="alert alert-success py-2">{{ __('Connected.') }} {{ __('Leave a field blank to keep what is saved.') }}</div>
                    @endif

                    <form method="POST" action="{{ route('business.integrations.update', $type) }}" autocomplete="off">
                        @csrf
                        @method('PUT')
                        @foreach ($typeFields as $field => $required)
                            <x-ui-form-input name="{{ $field }}" label="{{ $labels[$field] }}"
                                :type="$field === 'api_token' ? 'password' : 'text'" :required="$required && ! $integration" />
                        @endforeach
                        <x-ui-button variant="primary" type="submit">{{ __('Save') }}</x-ui-button>
                    </form>

                    @if ($integration)
                        <form method="POST" action="{{ route('business.integrations.destroy', $type) }}" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <x-ui-button variant="outline-danger" type="submit">{{ __('Disconnect') }}</x-ui-button>
                        </form>
                    @endif
                </x-ui-card>
            </div>
        @endforeach
    </div>
@endsection
