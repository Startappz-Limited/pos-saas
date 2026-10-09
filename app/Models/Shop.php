<?php

namespace App\Models;

use App\Enums\ShopStatus;
use App\Models\Concerns\Auditable;
use App\Models\Scopes\ShopAccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Shop extends Model
{
    use Auditable;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'business_id',
        'name',
        'code',
        'description',
        'phone',
        'email',
        'tax_pin',
        'vat_registered',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'latitude',
        'longitude',
        'status',
        'manager_id',
        'settings',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShopStatus::class,
            'settings' => 'array',
            'vat_registered' => 'boolean',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        // A shop is visible only to its business's admin and the staff
        // assigned to it (and to super-admins).
        static::addGlobalScope(new ShopAccessScope('id'));

        static::creating(function (self $shop) {
            if (empty($shop->invoice_code)) {
                $shop->assignInvoiceCode();
            }

            if (empty($shop->business_id)) {
                $shop->business_id = ShopAccessScope::currentUser()?->currentBusinessId()
                    ?? User::query()->whereKey($shop->manager_id)->value('business_id');
            }
            if (empty($shop->uuid)) {
                $shop->uuid = (string) Str::uuid();
            }
            if (Auth::check() && empty($shop->created_by)) {
                $shop->created_by = Auth::id();
            }
        });

        static::updating(function (self $shop) {
            // On a code change the shop needs an invoice code that is free
            // under its new code. (A shop from before invoice codes keeps its
            // old format until then; the new code may match another
            // business's shop.)
            if ($shop->isDirty('code') && (empty($shop->invoice_code) || $shop->invoiceCodeIsTaken())) {
                $shop->assignInvoiceCode();
            }

            if (Auth::check()) {
                $shop->updated_by = Auth::id();
            }
        });
    }

    protected static function booted(): void
    {
        // Who-can-see-which-shop is memoised per request; a new or removed
        // shop changes it.
        static::created(fn () => User::forgetShopAccessCache());
        static::deleted(fn () => User::forgetShopAccessCache());
        static::restored(fn () => User::forgetShopAccessCache());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Characters for invoice codes: no 0/O or 1/I, which are easy to misread on
     * a printed receipt. 32^3 = 32,768 codes per shop code.
     */
    public const INVOICE_CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * The shop code as it appears in invoice numbers (InvoiceNumberService).
     */
    public static function normalizedCode(?string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $code)) ?: 'SHOP';
    }

    /**
     * Give the shop a random 3-character invoice code that no other shop with
     * the same (normalised) shop code uses, in any business. Shop codes are
     * typed in and only unique per business; together with this code they
     * make every invoice number unique system-wide: INV-MAIN-7KQ-2026-000123.
     */
    public function assignInvoiceCode(): void
    {
        $taken = $this->invoiceCodesTakenForCode();

        do {
            $candidate = '';
            for ($i = 0; $i < 3; $i++) {
                $candidate .= self::INVOICE_CODE_ALPHABET[random_int(0, strlen(self::INVOICE_CODE_ALPHABET) - 1)];
            }
        } while ($taken->has($candidate));

        $this->invoice_code = $candidate;
    }

    /**
     * Whether another shop with the same (normalised) shop code already uses
     * this shop's invoice code.
     */
    public function invoiceCodeIsTaken(): bool
    {
        return $this->invoiceCodesTakenForCode()->has((string) $this->invoice_code);
    }

    /**
     * Invoice codes used by other shops with this shop's normalised code.
     *
     * @return Collection<string, int>
     */
    private function invoiceCodesTakenForCode(): Collection
    {
        $normalized = self::normalizedCode($this->code);

        return self::withoutGlobalScopes()
            ->whereNotNull('invoice_code')
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->get(['code', 'invoice_code'])
            ->filter(fn (self $shop): bool => self::normalizedCode($shop->code) === $normalized)
            ->pluck('invoice_code')
            ->flip();
    }

    // Relationships
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_user')
            ->withTimestamps();
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'shop_product')
            ->withPivot(['stock_quantity', 'reorder_level', 'cost_price', 'selling_price', 'wholesale_price', 'is_active'])
            ->withTimestamps();
    }

    public function ecommerceSyncs(): HasMany
    {
        return $this->hasMany(ProductEcommerceSync::class);
    }

    public function ecommerceOrders(): HasMany
    {
        return $this->hasMany(EcommerceOrder::class);
    }

    public function creditAccounts(): HasMany
    {
        return $this->hasMany(CreditAccount::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', ShopStatus::ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', ShopStatus::INACTIVE);
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', ShopStatus::SUSPENDED);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->hasShopRestrictions()) {
            return $query;
        }

        return $query->whereIn($this->qualifyColumn('id'), $user->accessibleShopIds());
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === ShopStatus::ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === ShopStatus::INACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === ShopStatus::SUSPENDED;
    }

    public function canProcessTransactions(): bool
    {
        return $this->status->canProcessTransactions();
    }

    public function activate(): bool
    {
        $this->status = ShopStatus::ACTIVE;

        return $this->save();
    }

    public function deactivate(): bool
    {
        $this->status = ShopStatus::INACTIVE;

        return $this->save();
    }

    public function suspend(): bool
    {
        $this->status = ShopStatus::SUSPENDED;

        return $this->save();
    }

    public function getFullAddressAttribute(): ?string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return ! empty($parts) ? implode(', ', $parts) : null;
    }

    // E-Commerce Integration Methods

    /**
     * Get integration configuration for a specific platform
     *
     * @param  string  $platform  Platform name (woocommerce, shopify)
     * @return array|null Configuration array or null if not set
     */
    public function getIntegrationConfig(string $platform): ?array
    {
        return $this->settings['integrations'][$platform] ?? null;
    }

    /**
     * Set integration configuration for a specific platform
     *
     * @param  string  $platform  Platform name (woocommerce, shopify)
     * @param  array  $config  Configuration array
     */
    public function setIntegrationConfig(string $platform, array $config): void
    {
        $settings = $this->settings ?? [];
        $settings['integrations'][$platform] = $config;
        $this->settings = $settings;
    }

    /**
     * Check if integration is connected for a specific platform
     *
     * @param  string  $platform  Platform name (woocommerce, shopify)
     * @return bool True if integration is enabled and has credentials
     */
    public function isIntegrationConnected(string $platform): bool
    {
        $config = $this->getIntegrationConfig($platform);

        if (! $config || ! ($config['enabled'] ?? false)) {
            return false;
        }

        // Check if required credentials exist
        if ($platform === 'woocommerce') {
            return ! empty($config['store_url'])
                && ! empty($config['consumer_key'])
                && ! empty($config['consumer_secret']);
        }

        if ($platform === 'shopify') {
            return ! empty($config['shop_domain'])
                && ! empty($config['access_token']);
        }

        return false;
    }

    /**
     * Get connection status for a specific platform
     *
     * @param  string  $platform  Platform name (woocommerce, shopify)
     * @return array Status array with connected flag and last tested timestamp
     */
    public function getConnectionStatus(string $platform): array
    {
        $config = $this->getIntegrationConfig($platform);

        return [
            'connected' => $this->isIntegrationConnected($platform),
            'enabled' => $config['enabled'] ?? false,
            'last_tested_at' => $config['last_tested_at'] ?? null,
        ];
    }

    // Sale Notification Settings

    /**
     * Master switch: whether a completed sale messages the customer at all.
     */
    public const SALE_NOTIFY_MASTER = 'all';

    /** Baileys — the invoice PDF sent as a WhatsApp document. */
    public const SALE_NOTIFY_INVOICE_PDF = 'invoice_pdf';

    /** Meta Cloud API — the plain-text receipt / credit balance / payment notice. */
    public const SALE_NOTIFY_TEXT_RECEIPT = 'text_receipt';

    /** @var array<int, string> Every switch the UI may toggle. */
    public const SALE_NOTIFY_CHANNELS = [
        self::SALE_NOTIFY_MASTER,
        self::SALE_NOTIFY_INVOICE_PDF,
        self::SALE_NOTIFY_TEXT_RECEIPT,
    ];

    /**
     * The shop's own setting for one switch, ignoring the master.
     *
     * This is what the UI renders, so that turning the master off and back on
     * restores each channel's previous position rather than resetting it.
     * Defaults preserve the behaviour that shipped before the switches existed.
     */
    public function saleNotificationEnabled(string $channel): bool
    {
        $default = match ($channel) {
            // Honours the pre-existing BAILEYS_NOTIFY_ON_SALE env default, and the
            // settings key used before these switches were grouped.
            self::SALE_NOTIFY_INVOICE_PDF => $this->settings['baileys']['notify_on_sale']
                ?? config('baileys.notify_on_sale', true),
            default => true,
        };

        return (bool) ($this->settings['sale_notifications'][$channel] ?? $default);
    }

    /**
     * Whether this shop notifies customers about sales at all (master switch).
     */
    public function notifiesOnSale(): bool
    {
        return $this->saleNotificationEnabled(self::SALE_NOTIFY_MASTER);
    }

    /**
     * Effective: does a completed sale WhatsApp the customer their invoice PDF?
     */
    public function notifiesInvoiceOnSale(): bool
    {
        return $this->notifiesOnSale()
            && $this->saleNotificationEnabled(self::SALE_NOTIFY_INVOICE_PDF);
    }

    /**
     * Effective: does a completed sale send the official Cloud API text receipt?
     */
    public function notifiesReceiptOnSale(): bool
    {
        return $this->notifiesOnSale()
            && $this->saleNotificationEnabled(self::SALE_NOTIFY_TEXT_RECEIPT);
    }

    /**
     * Turn one sale-notification switch on or off for this shop.
     */
    public function setSaleNotification(string $channel, bool $enabled): void
    {
        $settings = $this->settings ?? [];
        $settings['sale_notifications'][$channel] = $enabled;
        $this->settings = $settings;
    }

    /**
     * Terms applied to a credit account this shop auto-creates at the till.
     *
     * A credit sale for a wholesale customer with no account yet creates one on
     * the fly, so the debt can never be recorded on the sale but missed by the
     * ledger. Per-shop overrides live in `settings.credit`; the fallbacks are
     * config/credit.php.
     *
     * @return array{credit_limit: float, payment_terms_days: int, grace_period_days: int}
     */
    /**
     * Whether this shop is registered for VAT and must therefore charge it.
     *
     * The whole VAT engine is gated on this: an unregistered business charging
     * VAT is an offence, so shops stay opted out until someone sets the flag.
     */
    public function isVatRegistered(): bool
    {
        return (bool) $this->vat_registered;
    }

    /**
     * Per-shop tax overrides stored in `settings['tax']`.
     *
     * @return array<string, mixed>
     */
    public function taxSettings(): array
    {
        $settings = $this->settings;

        if (! is_array($settings)) {
            return [];
        }

        $tax = $settings['tax'] ?? [];

        return is_array($tax) ? $tax : [];
    }

    /**
     * Whether this shop quotes VAT-inclusive prices (the Kenyan retail norm).
     */
    public function pricesIncludeTax(): bool
    {
        return (bool) ($this->taxSettings()['prices_include_tax'] ?? config('tax.prices_include_tax', true));
    }

    public function creditDefaults(): array
    {
        $overrides = $this->settings['credit'] ?? [];

        return [
            'credit_limit' => (float) ($overrides['default_limit'] ?? config('credit.default_limit')),
            'payment_terms_days' => (int) ($overrides['default_payment_terms_days'] ?? config('credit.default_payment_terms_days')),
            'grace_period_days' => (int) ($overrides['default_grace_period_days'] ?? config('credit.default_grace_period_days')),
        ];
    }

    /**
     * Whether this shop rejects a credit sale that exceeds the customer's limit.
     *
     * When false the sale is still recorded and the caller receives a warning —
     * the ledger must never be less complete than the sales table.
     */
    public function enforcesCreditLimit(): bool
    {
        return (bool) ($this->settings['credit']['enforce_limit_at_pos']
            ?? config('credit.enforce_limit_at_pos'));
    }

    /**
     * Get all enabled integrations
     *
     * @return array Array of platform names that are enabled
     */
    public function getEnabledIntegrations(): array
    {
        $integrations = $this->settings['integrations'] ?? [];
        $enabled = [];

        foreach ($integrations as $platform => $config) {
            if ($config['enabled'] ?? false) {
                $enabled[] = $platform;
            }
        }

        return $enabled;
    }

    /**
     * Check if shop has any e-commerce integration enabled
     */
    public function hasAnyEcommerceIntegration(): bool
    {
        return count($this->getEnabledIntegrations()) > 0;
    }

    /**
     * Check if a specific platform integration is enabled
     *
     * @param  string  $platform  Platform name (woocommerce, shopify)
     */
    public function isIntegrationEnabled(string $platform): bool
    {
        $config = $this->getIntegrationConfig($platform);

        return (bool) ($config['enabled'] ?? false);
    }
}
