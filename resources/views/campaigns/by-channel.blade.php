@extends('layouts.app')

@section('title', 'Campaigns by Channel')

@section('content')
    <div class="card">
        <div class="card-header">
            <h4 class="mb-0">Campaigns by Channel</h4>
        </div>
        <div class="card-body">
            <table class="table align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Channel</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Spent</th>
                        <th class="text-end">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($byChannel as $row)
                        <tr>
                            <td>{{ $row->channel }}</td>
                            <td class="text-end">{{ $row->total }}</td>
                            <td class="text-end">{{ number_format((float) $row->total_spent, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $row->total_revenue, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No data.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
