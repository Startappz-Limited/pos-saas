<?php

namespace App\Http\Controllers;

use App\Enums\AuditEvent;
use App\Http\Requests\FilterAuditLogsRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Read-only access to the audit trail. There is deliberately no create, update or
 * delete path — audit rows are written only by the AuditLogger listener and are
 * immutable once stored.
 */
class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    public function index(FilterAuditLogsRequest $request): View
    {
        $filters = $request->filters();
        $user = $request->user();

        return view('audit-logs.index', [
            'logs' => $this->auditService->paginate($filters, $user, $request->integer('per_page') ?: 25),
            'statistics' => $this->auditService->statistics($filters, $user),
            'auditableTypes' => $this->auditService->auditableTypes($user),
            'events' => AuditEvent::cases(),
            'filters' => $request->validated(),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $this->authorize('view', $auditLog);

        return view('audit-logs.show', ['log' => $auditLog]);
    }

    /**
     * The full trail for one record, e.g. every change to a given sale.
     */
    public function forModel(Request $request, string $type, string $id): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $auditableType = $this->resolveAuditableType($type);

        return view('audit-logs.entity', [
            'logs' => $this->auditService->forAuditable($auditableType, $id, $request->user()),
            'auditableType' => $auditableType,
            'auditableUuid' => $id,
        ]);
    }

    /**
     * Everything a given user did.
     */
    public function forUser(Request $request, User $user): View
    {
        $this->authorize('viewAny', AuditLog::class);

        return view('audit-logs.actor', [
            'logs' => $this->auditService->forUser($user->id, $request->user()),
            'actor' => $user,
        ]);
    }

    /**
     * Stream the filtered trail as CSV. Streamed rather than built in memory so a
     * wide date range cannot exhaust the request.
     */
    public function export(FilterAuditLogsRequest $request): StreamedResponse
    {
        // Exporting the trail is a separate, narrower permission than reading it.
        $this->authorize('export', AuditLog::class);

        $filters = $request->filters();
        $user = $request->user();
        $filename = 'audit-log-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($filters, $user): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'Logged At', 'Entity', 'Entity UUID', 'Event', 'Status',
                'User', 'User Email', 'IP Address', 'Method', 'URL',
                'Old Values', 'New Values',
            ]);

            foreach ($this->auditService->stream($filters, $user) as $log) {
                fputcsv($handle, [
                    $log->created_at?->toDateTimeString(),
                    class_basename($log->auditable_type),
                    $log->auditable_uuid,
                    $log->event?->value,
                    $log->status?->value,
                    $log->user_name,
                    $log->user_email,
                    $log->ip_address,
                    $log->method,
                    $log->url,
                    json_encode($log->old_values),
                    json_encode($log->new_values),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Accept either a short name ("Sale") or a full class name in the URL, and only
     * ever resolve to a real app model — the value is user input.
     */
    private function resolveAuditableType(string $type): string
    {
        if (str_starts_with($type, 'App\\Models\\') && class_exists($type)) {
            return $type;
        }

        $candidate = 'App\\Models\\'.str_replace(['/', '\\', '.'], '', $type);

        abort_unless(class_exists($candidate), 404);

        return $candidate;
    }
}
