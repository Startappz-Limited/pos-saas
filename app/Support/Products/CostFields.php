<?php

namespace App\Support\Products;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Purchase costs in the flat API's product responses follow the permissions
 * (stage 10, step 1f): someone holding `products.view-cost` or
 * `products.set-cost` sees them; for anyone else every cost key (`cost_price`
 * on the product, its variations and its shop rows) is kept with the value
 * null, so the app's parsing doesn't change (it already reads a missing cost
 * as unknown).
 */
final class CostFields
{
    public const KEYS = ['cost_price'];

    public static function visibleTo(?User $user): bool
    {
        return $user !== null && $user->can('viewCost', Product::class);
    }

    /**
     * $data as the response sends it: unchanged for someone who may see costs,
     * otherwise as an array with every cost key nulled.
     */
    public static function forViewer(mixed $data, ?User $user): mixed
    {
        if (self::visibleTo($user)) {
            return $data;
        }

        return self::hide(json_decode(json_encode($data instanceof Arrayable ? $data->toArray() : $data), true));
    }

    private static function hide(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = in_array($key, self::KEYS, true) ? null : self::hide($item);
        }

        return $value;
    }
}
