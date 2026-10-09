<?php

namespace App\Http\Controllers;

use App\Enums\AlertType;
use App\Models\AbandonedCart;
use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(): View
    {
        return view('calendar.index');
    }

    /**
     * Return events as JSON for FullCalendar.
     */
    public function events(Request $request): JsonResponse
    {
        $start = $request->get('start');
        $end = $request->get('end');

        $query = Alert::with(['alertable', 'creator'])
            ->visibleTo($request->user())
            ->whereNotNull('scheduled_at');

        if ($start && $end) {
            $query->scheduledBetween($start, $end);
        } else {
            // Default to current month if no range provided to avoid unbounded query
            $query->scheduledBetween(now()->startOfMonth(), now()->endOfMonth()->addMonth());
        }

        $alerts = $query->orderBy('scheduled_at')->limit(500)->get();

        $events = $alerts->map(function (Alert $alert) {
            $color = match ($alert->type) {
                AlertType::ORDER_REMINDER, AlertType::CART_REMINDER => $alert->isOverdue() ? '#dc3545' : '#0d6efd',
                AlertType::ORDER_NOTE => '#198754',
                AlertType::ORDER_STATUS_CHANGED => '#6c757d',
                default => '#0dcaf0',
            };

            $url = null;
            if ($alert->alertable_type === 'App\\Models\\EcommerceOrder' && $alert->alertable) {
                $url = route('ecommerce-orders.show', $alert->alertable);
            }

            if ($alert->alertable_type === AbandonedCart::class && $alert->alertable) {
                $url = route('abandoned-carts.show', $alert->alertable);
            }

            return [
                'id' => $alert->id,
                'title' => $alert->title,
                'start' => $alert->scheduled_at->toIso8601String(),
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'message' => $alert->message,
                    'type' => $alert->type->label(),
                    'severity' => $alert->severity->label(),
                    'is_resolved' => $alert->is_resolved,
                    'is_overdue' => $alert->isOverdue(),
                    'creator' => $alert->creator?->name ?? 'System',
                    'url' => $url,
                    'order_number' => $alert->alertable?->order_number ?? null,
                ],
            ];
        });

        return response()->json($events);
    }
}
