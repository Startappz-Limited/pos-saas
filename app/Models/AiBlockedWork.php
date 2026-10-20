<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * AI work that could not run because the credit was spent (Phase 4). It waits
 * here and runs again after a top-up; the result is kept for the person who
 * asked.
 */
class AiBlockedWork extends Model
{
    use BelongsToBusiness;
    use BelongsToShop;

    public const WAITING = 'waiting';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const CAMPAIGN_CONTENT = 'campaign_content';

    protected static bool $shopOptional = true;

    protected $table = 'ai_blocked_work';

    protected $fillable = ['business_id', 'shop_id', 'requested_by', 'kind', 'payload', 'status', 'result', 'error', 'completed_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'result' => 'array', 'completed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $work): void {
            $work->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
