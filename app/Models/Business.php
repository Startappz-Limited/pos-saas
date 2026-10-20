<?php

namespace App\Models;

use App\Actions\Business\CopyRoleTemplates;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The tenant (decision 4.1): shops, products, lists and customers belong to a
 * business; people belong to it through memberships (4.4).
 */
class Business extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    /** Being closed (stage 3): nobody works in it; erased when the grace period ends. */
    public const STATUS_CLOSING = 'closing';

    /** Created at signup; opens when the owner's email is verified (stage 3). */
    public const STATUS_PENDING_VERIFICATION = 'pending_verification';

    protected $fillable = [
        'uuid',
        'name',
        'settings',
        'status',
        'primary_owner_id',
        'currency',
        'timezone',
        'logo_path',
        'receipt_header',
        'receipt_footer',
        'invoice_prefix',
        'trial_started_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_started_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $business): void {
            if (empty($business->uuid)) {
                $business->uuid = (string) Str::uuid();
            }
        });

        // Every sale needs a source, so a business starts with the default one,
        // and its own copies of the role templates (stage 3).
        static::created(function (self $business): void {
            app(CopyRoleTemplates::class)->execute($business);

            SaleSource::create([
                'business_id' => $business->id,
                'name' => 'Walk-in',
                'description' => 'Direct walk-in customer',
                'icon' => 'solar:shop-bold-duotone',
                'color' => '#6b7280',
                'is_active' => true,
                'sort_order' => 0,
            ]);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Whether scheduled sweeps run for this business (stage 1c): only an
     * active one. Suspended and closing businesses are skipped; read-only
     * (lapsed) businesses join them when billing arrives (Phase 4).
     */
    public function runsBackgroundWork(): bool
    {
        return $this->isActive();
    }

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(BusinessMembership::class);
    }

    /**
     * The founding owner: protected from other owners, the only one who
     * hands the business over.
     */
    public function primaryOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_owner_id');
    }

    /**
     * The logo as a data URI, for PDFs (dompdf can't reach the storage
     * link); null when none is set or the file is gone.
     */
    public function logoDataUri(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        $disk = Storage::disk(TenantFiles::PUBLIC_DISK);

        if (! $disk->exists($this->logo_path)) {
            return null;
        }

        return 'data:'.($disk->mimeType($this->logo_path) ?: 'image/png').';base64,'.base64_encode($disk->get($this->logo_path));
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(BusinessMembership::class)
            ->withPivot(['status', 'invited_by', 'joined_at'])
            ->withTimestamps();
    }
}
