<?php

namespace App\Http\Responses;

use App\Support\Logging\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\Honeypot\SpamResponder\SpamResponder;
use Symfony\Component\HttpFoundation\Response;

/**
 * A form the honeypot took for a bot. Nothing is saved; instead of the
 * package's blank page, the person is sent back to the form with a message,
 * since a real person who submits within a second (autofill) lands here too.
 * JSON requests get a 422.
 */
class HoneypotResponder implements SpamResponder
{
    public function respond(Request $request, Closure $next): Response
    {
        Log::warning('Honeypot caught a form submission', RequestContext::toArray());

        $message = __('Your form was sent too quickly to be accepted, so nothing was saved. Please check it and send it again.');

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()->withInput($request->except(['password', 'password_confirmation']))->with('error', $message);
    }
}
