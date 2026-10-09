@extends('layouts.app')

@section('title', 'Calendar')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.css" rel="stylesheet">
    <style>
        .fc .fc-toolbar-title {
            font-size: 1.25rem;
        }

        .fc .fc-button {
            font-size: 0.8125rem;
        }

        .fc-event {
            cursor: pointer;
        }

        .reminder-item.overdue {
            border-left: 3px solid #dc3545;
        }

        .reminder-item.upcoming {
            border-left: 3px solid #0d6efd;
        }

        .reminder-item.resolved {
            border-left: 3px solid #198754;
            opacity: 0.7;
        }
    </style>
@endpush

@section('content')

    <div class="row">
        <div class="col-xl-3">
            <div class="card">
                <div class="card-header border-bottom-dashed">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:alarm-bold-duotone" class="align-middle me-1 text-warning"></iconify-icon>
                        Upcoming Reminders
                    </h5>
                </div>
                <div class="card-body p-0" id="upcoming-reminders" style="max-height: 500px; overflow-y: auto;">
                    <div class="text-center text-muted py-4">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <p class="mb-0 mt-2">Loading...</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header border-bottom-dashed">
                    <h5 class="card-title mb-0">
                        <iconify-icon icon="solar:palette-round-bold-duotone"
                            class="align-middle me-1 text-info"></iconify-icon>
                        Legend
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-primary me-2">&nbsp;&nbsp;</span> Order Reminder
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-danger me-2">&nbsp;&nbsp;</span> Overdue Reminder
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-success me-2">&nbsp;&nbsp;</span> Order Note
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-secondary me-2">&nbsp;&nbsp;</span> Status Change
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-9">
            <div class="card">
                <div class="card-body">
                    <div id="calendar"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Detail Modal -->
    <div class="modal fade" id="eventDetailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventDetailTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3" id="eventDetailType"></div>
                    <div class="mb-3" id="eventDetailMessage"></div>
                    <div class="mb-3" id="eventDetailTime"></div>
                    <div class="mb-3" id="eventDetailCreator"></div>
                    <div class="mb-0" id="eventDetailStatus"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="btn btn-primary d-none" id="eventDetailLink">
                        <iconify-icon icon="solar:eye-bold-duotone" class="align-middle me-1"></iconify-icon>
                        View Order
                    </a>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            const modal = new bootstrap.Modal(document.getElementById('eventDetailModal'));

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
                },
                buttonText: {
                    today: 'Today',
                    month: 'Month',
                    week: 'Week',
                    day: 'Day',
                    list: 'List'
                },
                height: 'auto',
                handleWindowResize: true,
                events: {
                    url: '{{ route('calendar.events') }}',
                    method: 'GET',
                    failure: function() {
                        console.error('Failed to load calendar events');
                    }
                },
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    const props = info.event.extendedProps;

                    document.getElementById('eventDetailTitle').textContent = info.event.title;
                    document.getElementById('eventDetailType').innerHTML =
                        '<strong>Type:</strong> ' + (props.type || 'N/A');
                    document.getElementById('eventDetailMessage').innerHTML =
                        '<strong>Details:</strong> ' + (props.message || 'No details');
                    document.getElementById('eventDetailTime').innerHTML =
                        '<strong>Scheduled:</strong> ' + new Date(info.event.start).toLocaleString();
                    document.getElementById('eventDetailCreator').innerHTML =
                        '<strong>Created by:</strong> ' + (props.creator || 'System');

                    let statusHtml = '';
                    if (props.is_resolved) {
                        statusHtml = '<span class="badge bg-success">Resolved</span>';
                    } else if (props.is_overdue) {
                        statusHtml = '<span class="badge bg-danger">Overdue</span>';
                    } else {
                        statusHtml = '<span class="badge bg-primary">Active</span>';
                    }
                    document.getElementById('eventDetailStatus').innerHTML =
                        '<strong>Status:</strong> ' + statusHtml;

                    const link = document.getElementById('eventDetailLink');
                    if (props.url) {
                        link.href = props.url;
                        link.classList.remove('d-none');
                    } else {
                        link.classList.add('d-none');
                    }

                    modal.show();
                }
            });

            calendar.render();

            // Load upcoming reminders sidebar
            loadUpcomingReminders();

            function loadUpcomingReminders() {
                fetch('{{ route('calendar.events') }}?start=' + new Date().toISOString() + '&end=' + new Date(Date
                        .now() + 30 * 86400000).toISOString())
                    .then(r => r.json())
                    .then(events => {
                        const container = document.getElementById('upcoming-reminders');
                        const reminders = events.filter(e =>
                            e.extendedProps && !e.extendedProps.is_resolved
                        ).sort((a, b) => new Date(a.start) - new Date(b.start));

                        if (reminders.length === 0) {
                            container.innerHTML = '<div class="text-center text-muted py-4">' +
                                '<iconify-icon icon="solar:check-circle-bold-duotone" class="fs-36 mb-2 d-block text-success"></iconify-icon>' +
                                '<p class="mb-0">No upcoming reminders</p></div>';
                            return;
                        }

                        let html = '<div class="list-group list-group-flush">';
                        reminders.forEach(function(event) {
                            const isOverdue = event.extendedProps.is_overdue;
                            const stateClass = isOverdue ? 'overdue' : 'upcoming';
                            const badgeClass = isOverdue ? 'bg-danger' : 'bg-primary';
                            const date = new Date(event.start);

                            html += '<a href="' + (event.extendedProps.url || '#') +
                                '" class="list-group-item list-group-item-action reminder-item ' +
                                stateClass + ' p-3">' +
                                '<div class="d-flex justify-content-between align-items-start">' +
                                '<div>' +
                                '<h6 class="mb-1">' + event.title + '</h6>' +
                                (event.extendedProps.order_number ?
                                    '<small class="text-muted">Order #' + event.extendedProps
                                    .order_number + '</small><br>' : '') +
                                '<small class="text-muted">' + date.toLocaleDateString() + ' ' + date
                                .toLocaleTimeString([], {
                                    hour: '2-digit',
                                    minute: '2-digit'
                                }) + '</small>' +
                                '</div>' +
                                '<span class="badge ' + badgeClass + '">' + (isOverdue ? 'Overdue' :
                                    'Upcoming') + '</span>' +
                                '</div>' +
                                '</a>';
                        });
                        html += '</div>';
                        container.innerHTML = html;
                    })
                    .catch(function() {
                        document.getElementById('upcoming-reminders').innerHTML =
                            '<div class="text-center text-muted py-4"><p class="mb-0">Failed to load</p></div>';
                    });
            }
        });
    </script>
@endpush
