# 5. Calendar

View scheduled alerts, reminders, and events on a calendar interface.

---

## Get Calendar Events

`GET /api/calendar/events`

Returns events for the calendar view. Events are alert/reminder records with scheduled dates.

### Query Parameters

| Param | Type | Description |
|-------|------|-------------|
| `start` | datetime | Start of date range (ISO 8601) |
| `end` | datetime | End of date range (ISO 8601) |

If no range is provided, defaults to the current month + next month.

### Success Response `200`

```json
{
  "success": true,
  "data": [
    {
      "id": 42,
      "title": "Follow up delivery for #1042",
      "start": "2026-03-19T10:00:00.000000Z",
      "backgroundColor": "#0d6efd",
      "borderColor": "#0d6efd",
      "extendedProps": {
        "message": "Call customer to confirm address",
        "type": "Order Reminder",
        "severity": "Medium",
        "is_resolved": false,
        "is_overdue": false,
        "creator": "John Doe",
        "url": null,
        "order_number": "#1042"
      }
    },
    {
      "id": 43,
      "title": "Overdue reminder: Collect payment",
      "start": "2026-03-16T14:00:00.000000Z",
      "backgroundColor": "#dc3545",
      "borderColor": "#dc3545",
      "extendedProps": {
        "message": "Payment was due 2 days ago",
        "type": "Order Reminder",
        "severity": "High",
        "is_resolved": false,
        "is_overdue": true,
        "creator": "Jane Smith",
        "url": null,
        "order_number": "#1038"
      }
    }
  ]
}
```

### Event Color Mapping

| Type | Condition | Color |
|------|-----------|-------|
| Order Reminder | Overdue | `#dc3545` (red) |
| Order Reminder | Not overdue | `#0d6efd` (blue) |
| Order Note | — | `#198754` (green) |
| Order Status Changed | — | `#6c757d` (gray) |
| Other | — | `#0dcaf0` (cyan) |

---

## Flutter Implementation Notes

- Use a calendar widget (e.g., `table_calendar` package) to display events.
- Fetch events when the user navigates to a new month by sending `start` and `end` params.
- Tapping an event should show the event details (message, type, order info).
- Overdue events (red) should be shown at the top of the day's event list.
- If the event has an `order_number`, allow tapping to navigate to that order's detail screen.
- Maximum 500 events are returned per request.
