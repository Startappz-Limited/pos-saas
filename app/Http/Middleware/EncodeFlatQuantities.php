<?php

namespace App\Http\Middleware;

use App\Support\Quantities\Encode;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The flat API returns Eloquent models, whose quantities are scale-3 strings
 * ("2.000"). The contract (stage 11, step 8) keeps them JSON numbers, so this
 * middleware turns the quantity keys in `Encode::FLAT_KEYS` into numbers,
 * wherever they appear in the response. `/api/v1` is left alone: its
 * responses are built by hand and encoded there.
 */
class EncodeFlatQuantities
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/v1', 'api/v1/*') || ! $response instanceof JsonResponse) {
            return $response;
        }

        $body = $response->getData(true);

        if (! is_array($body)) {
            return $response;
        }

        $response->setData($this->encode($body));

        return $response;
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function encode(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->encode($item);
            } elseif (is_string($item) && in_array((string) $key, Encode::FLAT_KEYS, true) && is_numeric($item)) {
                $value[$key] = Encode::flat($item);
            }
        }

        return $value;
    }
}
