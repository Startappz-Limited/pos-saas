<?php

namespace App\Exceptions;

use App\Exceptions\Concerns\LogsAsRefusal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A request refused by a business rule, with a message meant for the person
 * who made it: "Return cannot be approved in its current status", "Customer
 * does not have a phone number". Rendered as a 422 on JSON requests and as an
 * error message back on the form on the web, never as a server error.
 *
 * Extends RuntimeException so code that already catches RuntimeException
 * keeps catching it.
 */
class BusinessRuleException extends RuntimeException
{
    use LogsAsRefusal;

    /** A machine-readable reason for the app (stage 10), sent as `code` on JSON. */
    public ?string $errorCode = null;

    public static function because(string $message, string $code): self
    {
        $exception = new self($message);
        $exception->errorCode = $code;

        return $exception;
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(array_filter([
                'success' => false,
                'message' => $this->getMessage(),
                'code' => $this->errorCode,
            ], fn ($value) => $value !== null), 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
