<?php

namespace App\Support\Tenancy;

use App\Models\Shop;
use Illuminate\Validation\ValidationException;

/**
 * The shop a signed-in person's new record goes to (hotfix fix/api-authz): their
 * own shop, else the first shop they can reach (`Shop::first()` runs through
 * ShopAccessScope, so it never crosses businesses, and is null for someone with
 * no shop). Writes that need a shop call require(), which refuses someone with
 * none instead of storing a null shop (a 500) or a hard-coded one (another
 * business's).
 */
final class ViewerShop
{
    public static function id(): ?int
    {
        $shopId = auth()->user()?->shop_id ?? Shop::first()?->id;

        return $shopId === null ? null : (int) $shopId;
    }

    /**
     * @throws ValidationException when the person has no shop
     */
    public static function require(): int
    {
        return self::id() ?? throw ValidationException::withMessages(['shop' => self::message()]);
    }

    public static function message(): string
    {
        return __('You haven\'t been given a shop yet. Ask the owner.');
    }
}
