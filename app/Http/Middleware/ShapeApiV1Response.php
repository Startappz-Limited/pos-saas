<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every `/api/v1` JSON response has the same keys (decision 4.8):
 * `success`, `message`, `data`, `meta` and `code`, whoever built it: a
 * controller (ApiV1), a middleware refusal, or the framework's own validation,
 * 404 and 401 responses. Keys already there are kept; only missing ones are
 * filled, and an error with no code gets the one for its status.
 */
class ShapeApiV1Response
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/v1', 'api/v1/*') || ! $response instanceof JsonResponse) {
            return $response;
        }

        $body = $response->getData(true);

        if (! is_array($body) || array_is_list($body)) {
            return $response;
        }

        $failed = $response->getStatusCode() >= 400;

        $response->setData([
            'success' => $body['success'] ?? ! $failed,
            'message' => $body['message'] ?? null,
            'data' => $body['data'] ?? null,
            'meta' => $body['meta'] ?? null,
            'code' => $body['code'] ?? ($failed ? ApiErrorCode::forStatus($response->getStatusCode())->value : null),
            ...array_diff_key($body, array_flip(['success', 'message', 'data', 'meta', 'code'])),
        ]);

        return $response;
    }
}
