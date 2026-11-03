<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in person's in-app notifications (stage 10): approval requests,
 * reminders and outcomes, under the topbar's bell. Only their own, and only
 * the current business's: one login can belong to several businesses.
 */
class NotificationController extends Controller
{
    public function __construct(private TenantContext $tenant) {}

    /**
     * Marks the notification read and opens what it is about.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $found = $request->user()->notifications()
            ->where('data->business_id', $this->tenant->id())
            ->findOrFail($notification);

        $found->markAsRead();

        return redirect()->to($this->localUrl($found->data['url'] ?? null));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->where('data->business_id', $this->tenant->id())
            ->update(['read_at' => now()]);

        return back();
    }

    /**
     * Only a path on this site, never somewhere else a notification might name.
     */
    private function localUrl(?string $url): string
    {
        $path = parse_url((string) $url, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//')
            ? $path.(($query = parse_url((string) $url, PHP_URL_QUERY)) ? '?'.$query : '')
            : route('dashboard');
    }
}
