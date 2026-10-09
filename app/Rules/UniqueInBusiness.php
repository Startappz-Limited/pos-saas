<?php

namespace App\Rules;

use App\Models\Scopes\ShopAccessScope;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * A `unique:` rule for a business-owned table (suppliers, categories, ...):
 * the value only has to be unique within the current user's business, which
 * matches the [business_id, column] database index.
 */
class UniqueInBusiness
{
    public static function for(string $table, string $column): Unique
    {
        $businessId = ShopAccessScope::currentUser()?->currentBusinessId();

        return Rule::unique($table, $column)->where(
            fn ($query) => $query->where('business_id', $businessId)
        );
    }
}
