<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

/**
 * One metered AI call, top-up or refund (Phase 4): business, shop, provider,
 * model, tokens and credits.
 */
class AiUsageEvent extends Model
{
    use BelongsToBusiness;
    use BelongsToShop;

    protected static bool $shopOptional = true;

    protected $fillable = ['business_id', 'shop_id', 'provider', 'model', 'tokens_in', 'tokens_out', 'credits', 'kind', 'status', 'user_id'];

    protected function casts(): array
    {
        return ['credits' => 'decimal:4'];
    }
}
