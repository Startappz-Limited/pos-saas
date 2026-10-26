<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A till (device) in a shop (decision 4.2). Cash sessions (CashRegister) run
 * on a register, and each register numbers its invoices in its own series,
 * `INV-{shop code}-{register code}-{year}-{sequence}` (4.9).
 *
 * Every shop has a default register, created with the shop: the existing app
 * knows nothing about registers, so legacy cash-register and sale calls land
 * on it (4.8).
 */
class Register extends Model
{
    use BelongsToShop;
    use HasFactory;

    /** The default register's first code; it changes if the till is re-paired (Phase 7). */
    public const DEFAULT_CODE = 'R1';

    protected $fillable = [
        'uuid',
        'shop_id',
        'name',
        'code',
        'is_default',
        'device_id',
        'paired_at',
        'last_synced_at',
        'sync_overdue_warned_at',
        'last_invoice_number',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'paired_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'sync_overdue_warned_at' => 'datetime',
            'last_invoice_number' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $register): void {
            if (empty($register->uuid)) {
                $register->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The shop's default register, created if it has none yet. Found
     * regardless of the viewer's shop access: the shop is the caller's.
     */
    public static function defaultFor(int $shopId): self
    {
        $id = DB::table('registers')->where('shop_id', $shopId)->where('is_default', true)->value('id');

        if ($id === null) {
            DB::table('registers')->insertOrIgnore([
                'uuid' => (string) Str::uuid(),
                'shop_id' => $shopId,
                'name' => 'Main till',
                'code' => self::DEFAULT_CODE,
                'is_default' => true,
                'last_invoice_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $id = DB::table('registers')->where('shop_id', $shopId)->where('is_default', true)->orderBy('id')->value('id');
        }

        // Hydrated from the row: no scope is lifted.
        return (new self)->newFromBuilder((array) DB::table('registers')->where('id', $id)->first());
    }

    /**
     * Paired tills that haven't synced for `sync.overdue_hours` (Phase 7): a
     * till never synced counts from its pairing.
     */
    public function scopeSyncOverdue(Builder $query): Builder
    {
        $before = now()->subHours((int) config('sync.overdue_hours'));

        return $query->whereNotNull('registers.device_id')
            ->whereRaw('COALESCE(registers.last_synced_at, registers.paired_at) < ?', [$before]);
    }

    public function isSyncOverdue(): bool
    {
        $since = $this->last_synced_at ?? $this->paired_at;

        return $this->device_id !== null && $since !== null
            && $since->lessThan(now()->subHours((int) config('sync.overdue_hours')));
    }

    /**
     * Notes a sync now: the till is no longer overdue, and the next silence
     * warns its owners again.
     */
    public function markSynced(): void
    {
        $this->forceFill(['last_synced_at' => now(), 'sync_overdue_warned_at' => null])->saveQuietly();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(CashRegister::class);
    }
}
