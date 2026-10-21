<?php

namespace App\Http\Controllers;

use App\Exceptions\BusinessRuleException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * The response for an action that failed inside a try block, so every
     * failure reaches the person with the right status:
     *
     * - validation, authorization, "not found", HTTP and self-rendering
     *   exceptions (read-only, plan limits…) are thrown on, keeping their own
     *   status and field errors;
     * - a business rule (BusinessRuleException) is a 422 with its message, or
     *   the message back on the form;
     * - anything else is logged as an error (with the user, business and
     *   route) and answered with a generic message (a 500 on JSON): its text
     *   may hold internals such as SQL.
     *
     * Call it after rolling back any transaction the action opened.
     *
     * @throws Throwable the exceptions that render themselves
     */
    protected function failed(Throwable $e, ?string $prefix = null): JsonResponse|RedirectResponse
    {
        if ($e instanceof ValidationException
            || $e instanceof AuthorizationException
            || $e instanceof AuthenticationException
            || $e instanceof ModelNotFoundException
            || $e instanceof TokenMismatchException
            || $e instanceof HttpExceptionInterface
            || (method_exists($e, 'render') && ! $e instanceof BusinessRuleException)) {
            throw $e;
        }

        $expected = $e instanceof BusinessRuleException;

        // A business rule logs itself at notice (LogsAsRefusal); anything else
        // as an error with its stack trace and the request's context.
        report($e);

        $message = $expected
            ? $e->getMessage()
            : __('Something went wrong on our side and nothing was saved. Please try again; if it keeps happening, contact support.');
        $message = $prefix !== null ? "{$prefix} {$message}" : $message;

        if (request()->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], $expected ? 422 : 500);
        }

        return back()->withInput()->with('error', $message);
    }
}
