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

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
            ], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
