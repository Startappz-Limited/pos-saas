<?php

namespace App\Http\Responses;

use App\Enums\ApiErrorCode;
use Illuminate\Http\JsonResponse;

/**
 * The `/api/v1` response shape (decision 4.8):
 * `{success, message, data, meta, code}`. `code` is null on success and an
 * ApiErrorCode on failure; `meta` holds paging, sync cursors and flags.
 */
final class ApiV1
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public static function ok(mixed $data = null, ?string $message = null, ?array $meta = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'code' => null,
        ], $status);
    }

    /**
     * @param  array<string, list<string>>|null  $errors  field errors, as validation reports them
     * @param  array<string, mixed>  $extra  further keys a client may read (e.g. `limit`)
     */
    public static function error(ApiErrorCode $code, string $message, int $status, ?array $errors = null, array $extra = []): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'data' => null,
            'meta' => null,
            'code' => $code->value,
            'errors' => $errors,
            ...$extra,
        ], fn ($value, string $key): bool => $key !== 'errors' || $value !== null, ARRAY_FILTER_USE_BOTH), $status);
    }
}
