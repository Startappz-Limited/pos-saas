# Module 15: Advertising & Marketing ROI
## Stock Taking & Sales Management System

> **Important:** This module follows the guidelines defined in `.ai/general/` folder.

### Module Overview
Complete advertising and marketing campaign tracking with cost management, performance metrics, ROI calculation, and analytics to measure marketing effectiveness across shops and channels.

**Priority:** P2 (Medium)  
**Dependencies:** Modules 03, 10, 14  
**Estimated Time:** 2 days

---

## 1. Guidelines Compliance

| Guideline | Compliance |
|-----------|------------|
| **0.3 Database Design** | Dual ID (`id` + `uuid`), audit columns, Enum status |
| **0.4 Roles & Permissions** | Spatie `{module}.{action}` format |
| **0.5 Audit Logging** | Auditable trait on Campaign model |
| **0.8 Routing** | UUID-only route binding |
| **0.9 Coding Standards** | Actions + Services pattern |
| **1.0 Security** | Form Requests, Policies |

---

## 2. Database Schema

### Campaigns Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Relationships
            $table->foreignId('shop_id')->nullable()->constrained()->nullOnDelete();
            
            // Campaign details
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('campaign_type'); // CampaignType enum
            $table->string('channel'); // MarketingChannel enum
            
            // Budget
            $table->decimal('budget', 15, 2);
            $table->decimal('spent', 15, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            
            // Duration
            $table->date('start_date');
            $table->date('end_date');
            
            // Goals
            $table->decimal('target_revenue', 15, 2)->nullable();
            $table->integer('target_conversions')->nullable();
            $table->integer('target_reach')->nullable();
            
            // Results (calculated/updated)
            $table->decimal('actual_revenue', 15, 2)->default(0);
            $table->integer('conversions')->default(0);
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('reach')->default(0);
            
            // Status
            $table->string('status'); // CampaignStatus enum
            
            // Tracking
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('promo_code')->nullable();
            
            $table->text('notes')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('uuid');
            $table->index('code');
            $table->index('promo_code');
            $table->index(['shop_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
```

### Campaign Expenses Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_expenses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('expense_type'); // CampaignExpenseType enum
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->date('expense_date');
            $table->string('vendor')->nullable();
            $table->string('invoice_number')->nullable();
            
            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index('campaign_id');
            $table->index('expense_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_expenses');
    }
};
```

### Campaign Conversions Table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_conversions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->string('conversion_type'); // ConversionType enum
            $table->decimal('revenue', 15, 2)->default(0);
            $table->string('source')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamp('converted_at');
            $table->timestamps();
            
            $table->index('campaign_id');
            $table->index('converted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_conversions');
    }
};
```

### Campaign Metrics Table (Daily Snapshots)

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_metrics', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('cost', 15, 2)->default(0);
            
            // Calculated metrics
            $table->decimal('ctr', 8, 4)->default(0); // Click-through rate
            $table->decimal('conversion_rate', 8, 4)->default(0);
            $table->decimal('cpc', 10, 2)->default(0); // Cost per click
            $table->decimal('cpa', 10, 2)->default(0); // Cost per acquisition
            $table->decimal('roas', 10, 4)->default(0); // Return on ad spend
            
            $table->timestamps();
            
            $table->unique(['campaign_id', 'metric_date']);
            $table->index('metric_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_metrics');
    }
};
```

---

## 3. Enums

### CampaignType Enum

**File:** `app/Enums/CampaignType.php`

```php
<?php

namespace App\Enums;

enum CampaignType: string
{
    case AWARENESS = 'awareness';
    case LEAD_GENERATION = 'lead_generation';
    case SALES = 'sales';
    case ENGAGEMENT = 'engagement';
    case RETENTION = 'retention';
    case PRODUCT_LAUNCH = 'product_launch';
    case SEASONAL = 'seasonal';
    case PROMOTION = 'promotion';

    public function label(): string
    {
        return match ($this) {
            self::AWARENESS => 'Brand Awareness',
            self::LEAD_GENERATION => 'Lead Generation',
            self::SALES => 'Direct Sales',
            self::ENGAGEMENT => 'Engagement',
            self::RETENTION => 'Customer Retention',
            self::PRODUCT_LAUNCH => 'Product Launch',
            self::SEASONAL => 'Seasonal',
            self::PROMOTION => 'Promotion',
        };
    }

    public function primaryMetric(): string
    {
        return match ($this) {
            self::AWARENESS => 'impressions',
            self::LEAD_GENERATION => 'conversions',
            self::SALES => 'revenue',
            self::ENGAGEMENT => 'clicks',
            self::RETENTION => 'conversions',
            self::PRODUCT_LAUNCH => 'reach',
            self::SEASONAL => 'revenue',
            self::PROMOTION => 'revenue',
        };
    }
}
```

### MarketingChannel Enum

**File:** `app/Enums/MarketingChannel.php`

```php
<?php

namespace App\Enums;

enum MarketingChannel: string
{
    case SOCIAL_MEDIA = 'social_media';
    case GOOGLE_ADS = 'google_ads';
    case EMAIL = 'email';
    case SMS = 'sms';
    case RADIO = 'radio';
    case TV = 'tv';
    case PRINT = 'print';
    case BILLBOARD = 'billboard';
    case INFLUENCER = 'influencer';
    case REFERRAL = 'referral';
    case DIRECT = 'direct';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::SOCIAL_MEDIA => 'Social Media',
            self::GOOGLE_ADS => 'Google Ads',
            self::EMAIL => 'Email Marketing',
            self::SMS => 'SMS Marketing',
            self::RADIO => 'Radio',
            self::TV => 'Television',
            self::PRINT => 'Print Media',
            self::BILLBOARD => 'Billboard/Outdoor',
            self::INFLUENCER => 'Influencer Marketing',
            self::REFERRAL => 'Referral Program',
            self::DIRECT => 'Direct Marketing',
            self::OTHER => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SOCIAL_MEDIA => 'solar:users-group-two-rounded-bold',
            self::GOOGLE_ADS => 'mdi:google-ads',
            self::EMAIL => 'solar:letter-bold',
            self::SMS => 'solar:phone-bold',
            self::RADIO => 'solar:radio-bold',
            self::TV => 'solar:tv-bold',
            self::PRINT => 'solar:newspaper-bold',
            self::BILLBOARD => 'solar:map-bold',
            self::INFLUENCER => 'solar:star-bold',
            self::REFERRAL => 'solar:share-bold',
            self::DIRECT => 'solar:hand-shake-bold',
            self::OTHER => 'solar:widget-bold',
        };
    }
}
```

### CampaignStatus Enum

**File:** `app/Enums/CampaignStatus.php`

```php
<?php

namespace App\Enums;

enum CampaignStatus: string
{
    case DRAFT = 'draft';
    case SCHEDULED = 'scheduled';
    case ACTIVE = 'active';
    case PAUSED = 'paused';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SCHEDULED => 'Scheduled',
            self::ACTIVE => 'Active',
            self::PAUSED => 'Paused',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DRAFT => 'secondary',
            self::SCHEDULED => 'info',
            self::ACTIVE => 'success',
            self::PAUSED => 'warning',
            self::COMPLETED => 'primary',
            self::CANCELLED => 'danger',
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::SCHEDULED, self::PAUSED]);
    }
}
```

### CampaignExpenseType Enum

**File:** `app/Enums/CampaignExpenseType.php`

```php
<?php

namespace App\Enums;

enum CampaignExpenseType: string
{
    case AD_SPEND = 'ad_spend';
    case CREATIVE = 'creative';
    case PRODUCTION = 'production';
    case AGENCY_FEE = 'agency_fee';
    case INFLUENCER_FEE = 'influencer_fee';
    case PLATFORM_FEE = 'platform_fee';
    case PRINTING = 'printing';
    case DISTRIBUTION = 'distribution';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AD_SPEND => 'Ad Spend',
            self::CREATIVE => 'Creative/Design',
            self::PRODUCTION => 'Production',
            self::AGENCY_FEE => 'Agency Fee',
            self::INFLUENCER_FEE => 'Influencer Fee',
            self::PLATFORM_FEE => 'Platform Fee',
            self::PRINTING => 'Printing',
            self::DISTRIBUTION => 'Distribution',
            self::OTHER => 'Other',
        };
    }
}
```

### ConversionType Enum

**File:** `app/Enums/ConversionType.php`

```php
<?php

namespace App\Enums;

enum ConversionType: string
{
    case PURCHASE = 'purchase';
    case SIGNUP = 'signup';
    case LEAD = 'lead';
    case DOWNLOAD = 'download';
    case VISIT = 'visit';
    case CALL = 'call';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::SIGNUP => 'Sign Up',
            self::LEAD => 'Lead Generated',
            self::DOWNLOAD => 'Download',
            self::VISIT => 'Store Visit',
            self::CALL => 'Phone Call',
        };
    }
}
```

---

## 4. Models

### Campaign Model

**File:** `app/Models/Campaign.php`

```php
<?php

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\MarketingChannel;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'uuid',
        'shop_id',
        'name',
        'code',
        'description',
        'campaign_type',
        'channel',
        'budget',
        'spent',
        'currency',
        'start_date',
        'end_date',
        'target_revenue',
        'target_conversions',
        'target_reach',
        'actual_revenue',
        'conversions',
        'impressions',
        'clicks',
        'reach',
        'status',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'tracking_url',
        'promo_code',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'campaign_type' => CampaignType::class,
            'channel' => MarketingChannel::class,
            'status' => CampaignStatus::class,
            'budget' => 'decimal:2',
            'spent' => 'decimal:2',
            'target_revenue' => 'decimal:2',
            'actual_revenue' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $campaign) {
            if (empty($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
            if (empty($campaign->code)) {
                $campaign->code = self::generateCode();
            }
            if (empty($campaign->status)) {
                $campaign->status = CampaignStatus::DRAFT;
            }
            $campaign->created_by = auth()->id();
        });

        static::updating(function (self $campaign) {
            $campaign->updated_by = auth()->id();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function generateCode(): string
    {
        $prefix = 'CMP';
        $year = now()->format('y');
        $sequence = self::whereYear('created_at', now()->year)->count() + 1;
        return "{$prefix}{$year}" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Relationships

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(CampaignExpense::class);
    }

    public function conversionsRecords(): HasMany
    {
        return $this->hasMany(CampaignConversion::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(CampaignMetric::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Calculated Attributes

    public function getRoiAttribute(): float
    {
        if ($this->spent <= 0) {
            return 0;
        }
        return (($this->actual_revenue - $this->spent) / $this->spent) * 100;
    }

    public function getRoasAttribute(): float
    {
        if ($this->spent <= 0) {
            return 0;
        }
        return $this->actual_revenue / $this->spent;
    }

    public function getCtrAttribute(): float
    {
        if ($this->impressions <= 0) {
            return 0;
        }
        return ($this->clicks / $this->impressions) * 100;
    }

    public function getConversionRateAttribute(): float
    {
        if ($this->clicks <= 0) {
            return 0;
        }
        return ($this->conversions / $this->clicks) * 100;
    }

    public function getCpcAttribute(): float
    {
        if ($this->clicks <= 0) {
            return 0;
        }
        return $this->spent / $this->clicks;
    }

    public function getCpaAttribute(): float
    {
        if ($this->conversions <= 0) {
            return 0;
        }
        return $this->spent / $this->conversions;
    }

    public function getBudgetUtilizationAttribute(): float
    {
        if ($this->budget <= 0) {
            return 0;
        }
        return ($this->spent / $this->budget) * 100;
    }

    public function getRemainingBudgetAttribute(): float
    {
        return max(0, $this->budget - $this->spent);
    }

    public function getRevenueProgressAttribute(): float
    {
        if (!$this->target_revenue || $this->target_revenue <= 0) {
            return 0;
        }
        return ($this->actual_revenue / $this->target_revenue) * 100;
    }

    public function getDaysRemainingAttribute(): int
    {
        if ($this->end_date->isPast()) {
            return 0;
        }
        return now()->diffInDays($this->end_date);
    }

    public function getDurationDaysAttribute(): int
    {
        return $this->start_date->diffInDays($this->end_date);
    }

    // Status Methods

    public function isActive(): bool
    {
        return $this->status === CampaignStatus::ACTIVE;
    }

    public function activate(): void
    {
        $this->update(['status' => CampaignStatus::ACTIVE]);
    }

    public function pause(): void
    {
        $this->update(['status' => CampaignStatus::PAUSED]);
    }

    public function complete(): void
    {
        $this->update(['status' => CampaignStatus::COMPLETED]);
    }

    public function cancel(): void
    {
        $this->update(['status' => CampaignStatus::CANCELLED]);
    }

    // Scopes

    public function scopeForShop($query, int $shopId)
    {
        return $query->where('shop_id', $shopId);
    }

    public function scopeStatus($query, CampaignStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->where('status', CampaignStatus::ACTIVE);
    }

    public function scopeRunning($query)
    {
        return $query->whereIn('status', [CampaignStatus::ACTIVE, CampaignStatus::SCHEDULED])
                     ->where('start_date', '<=', now())
                     ->where('end_date', '>=', now());
    }

    public function scopeChannel($query, MarketingChannel $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeType($query, CampaignType $type)
    {
        return $query->where('campaign_type', $type);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->where('start_date', '>=', $startDate)
                     ->where('end_date', '<=', $endDate);
    }

    public function scopeWithPromoCode($query, string $code)
    {
        return $query->where('promo_code', $code);
    }
}
```

### CampaignExpense Model

**File:** `app/Models/CampaignExpense.php`

```php
<?php

namespace App\Models;

use App\Enums\CampaignExpenseType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CampaignExpense extends Model
{
    protected $fillable = [
        'uuid',
        'campaign_id',
        'expense_id',
        'expense_type',
        'description',
        'amount',
        'expense_date',
        'vendor',
        'invoice_number',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expense_type' => CampaignExpenseType::class,
            'amount' => 'decimal:2',
            'expense_date' => 'date',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $expense) {
            if (empty($expense->uuid)) {
                $expense->uuid = (string) Str::uuid();
            }
            $expense->created_by = auth()->id();
        });

        static::created(function (self $expense) {
            $expense->campaign->increment('spent', $expense->amount);
        });

        static::deleted(function (self $expense) {
            $expense->campaign->decrement('spent', $expense->amount);
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}
```

### CampaignConversion Model

**File:** `app/Models/CampaignConversion.php`

```php
<?php

namespace App\Models;

use App\Enums\ConversionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CampaignConversion extends Model
{
    protected $fillable = [
        'uuid',
        'campaign_id',
        'sale_id',
        'customer_id',
        'conversion_type',
        'revenue',
        'source',
        'metadata',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'conversion_type' => ConversionType::class,
            'revenue' => 'decimal:2',
            'metadata' => 'array',
            'converted_at' => 'datetime',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $conversion) {
            if (empty($conversion->uuid)) {
                $conversion->uuid = (string) Str::uuid();
            }
            if (empty($conversion->converted_at)) {
                $conversion->converted_at = now();
            }
        });

        static::created(function (self $conversion) {
            $conversion->campaign->increment('conversions');
            $conversion->campaign->increment('actual_revenue', $conversion->revenue);
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
```

### CampaignMetric Model

**File:** `app/Models/CampaignMetric.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignMetric extends Model
{
    protected $fillable = [
        'campaign_id',
        'metric_date',
        'impressions',
        'clicks',
        'conversions',
        'revenue',
        'cost',
        'ctr',
        'conversion_rate',
        'cpc',
        'cpa',
        'roas',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'revenue' => 'decimal:2',
            'cost' => 'decimal:2',
            'ctr' => 'decimal:4',
            'conversion_rate' => 'decimal:4',
            'cpc' => 'decimal:2',
            'cpa' => 'decimal:2',
            'roas' => 'decimal:4',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function calculateMetrics(): void
    {
        $this->ctr = $this->impressions > 0 ? ($this->clicks / $this->impressions) * 100 : 0;
        $this->conversion_rate = $this->clicks > 0 ? ($this->conversions / $this->clicks) * 100 : 0;
        $this->cpc = $this->clicks > 0 ? $this->cost / $this->clicks : 0;
        $this->cpa = $this->conversions > 0 ? $this->cost / $this->conversions : 0;
        $this->roas = $this->cost > 0 ? $this->revenue / $this->cost : 0;
    }
}
```

---

## 5. Actions

### CreateCampaignAction

**File:** `app/Actions/Campaigns/CreateCampaignAction.php`

```php
<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignType;
use App\Enums\MarketingChannel;
use App\Models\Campaign;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreateCampaignAction
{
    public function execute(
        string $name,
        CampaignType $type,
        MarketingChannel $channel,
        float $budget,
        Carbon $startDate,
        Carbon $endDate,
        ?Shop $shop = null,
        array $additionalData = []
    ): Campaign {
        return DB::transaction(function () use ($name, $type, $channel, $budget, $startDate, $endDate, $shop, $additionalData) {
            return Campaign::create([
                'shop_id' => $shop?->id,
                'name' => $name,
                'campaign_type' => $type,
                'channel' => $channel,
                'budget' => $budget,
                'start_date' => $startDate,
                'end_date' => $endDate,
                ...$additionalData,
            ]);
        });
    }
}
```

### RecordConversionAction

**File:** `app/Actions/Campaigns/RecordConversionAction.php`

```php
<?php

namespace App\Actions\Campaigns;

use App\Enums\ConversionType;
use App\Models\Campaign;
use App\Models\CampaignConversion;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class RecordConversionAction
{
    public function execute(
        Campaign $campaign,
        ConversionType $type,
        float $revenue = 0,
        ?Sale $sale = null,
        ?int $customerId = null,
        ?string $source = null,
        array $metadata = []
    ): CampaignConversion {
        return DB::transaction(function () use ($campaign, $type, $revenue, $sale, $customerId, $source, $metadata) {
            return CampaignConversion::create([
                'campaign_id' => $campaign->id,
                'sale_id' => $sale?->id,
                'customer_id' => $customerId,
                'conversion_type' => $type,
                'revenue' => $revenue,
                'source' => $source,
                'metadata' => $metadata,
            ]);
        });
    }
}
```

### AddCampaignExpenseAction

**File:** `app/Actions/Campaigns/AddCampaignExpenseAction.php`

```php
<?php

namespace App\Actions\Campaigns;

use App\Enums\CampaignExpenseType;
use App\Models\Campaign;
use App\Models\CampaignExpense;
use App\Models\Expense;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AddCampaignExpenseAction
{
    public function execute(
        Campaign $campaign,
        CampaignExpenseType $type,
        string $description,
        float $amount,
        Carbon $date,
        ?Expense $linkedExpense = null,
        ?string $vendor = null,
        ?string $invoiceNumber = null
    ): CampaignExpense {
        return DB::transaction(function () use ($campaign, $type, $description, $amount, $date, $linkedExpense, $vendor, $invoiceNumber) {
            return CampaignExpense::create([
                'campaign_id' => $campaign->id,
                'expense_id' => $linkedExpense?->id,
                'expense_type' => $type,
                'description' => $description,
                'amount' => $amount,
                'expense_date' => $date,
                'vendor' => $vendor,
                'invoice_number' => $invoiceNumber,
            ]);
        });
    }
}
```

### UpdateCampaignMetricsAction

**File:** `app/Actions/Campaigns/UpdateCampaignMetricsAction.php`

```php
<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignMetric;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class UpdateCampaignMetricsAction
{
    public function execute(
        Campaign $campaign,
        Carbon $date,
        int $impressions = 0,
        int $clicks = 0,
        int $conversions = 0,
        float $revenue = 0,
        float $cost = 0
    ): CampaignMetric {
        return DB::transaction(function () use ($campaign, $date, $impressions, $clicks, $conversions, $revenue, $cost) {
            $metric = CampaignMetric::updateOrCreate(
                [
                    'campaign_id' => $campaign->id,
                    'metric_date' => $date->toDateString(),
                ],
                [
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'conversions' => $conversions,
                    'revenue' => $revenue,
                    'cost' => $cost,
                ]
            );

            $metric->calculateMetrics();
            $metric->save();

            // Update campaign totals
            $this->updateCampaignTotals($campaign);

            return $metric;
        });
    }

    private function updateCampaignTotals(Campaign $campaign): void
    {
        $totals = $campaign->metrics()->selectRaw('
            SUM(impressions) as total_impressions,
            SUM(clicks) as total_clicks,
            SUM(conversions) as total_conversions,
            SUM(revenue) as total_revenue
        ')->first();

        $campaign->update([
            'impressions' => $totals->total_impressions ?? 0,
            'clicks' => $totals->total_clicks ?? 0,
            'conversions' => $totals->total_conversions ?? 0,
            'actual_revenue' => $totals->total_revenue ?? 0,
        ]);
    }
}
```

---

## 6. Services

### CampaignService

**File:** `app/Services/CampaignService.php`

```php
<?php

namespace App\Services;

use App\Actions\Campaigns\AddCampaignExpenseAction;
use App\Actions\Campaigns\CreateCampaignAction;
use App\Actions\Campaigns\RecordConversionAction;
use App\Actions\Campaigns\UpdateCampaignMetricsAction;
use App\Enums\CampaignExpenseType;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\ConversionType;
use App\Enums\MarketingChannel;
use App\Models\Campaign;
use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CampaignService
{
    public function __construct(
        private CreateCampaignAction $createCampaign,
        private RecordConversionAction $recordConversion,
        private AddCampaignExpenseAction $addExpense,
        private UpdateCampaignMetricsAction $updateMetrics
    ) {}

    /**
     * Create new campaign
     */
    public function create(array $data): Campaign
    {
        return $this->createCampaign->execute(
            $data['name'],
            CampaignType::from($data['campaign_type']),
            MarketingChannel::from($data['channel']),
            $data['budget'],
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
            isset($data['shop_id']) ? Shop::find($data['shop_id']) : null,
            collect($data)->except(['name', 'campaign_type', 'channel', 'budget', 'start_date', 'end_date', 'shop_id'])->toArray()
        );
    }

    /**
     * Update campaign
     */
    public function update(Campaign $campaign, array $data): Campaign
    {
        if (!$campaign->status->isEditable()) {
            throw new \Exception('Campaign cannot be edited in current status.');
        }

        $campaign->update($data);
        return $campaign->refresh();
    }

    /**
     * Activate campaign
     */
    public function activate(Campaign $campaign): Campaign
    {
        if ($campaign->status !== CampaignStatus::DRAFT && $campaign->status !== CampaignStatus::SCHEDULED) {
            throw new \Exception('Only draft or scheduled campaigns can be activated.');
        }

        $campaign->activate();
        return $campaign->refresh();
    }

    /**
     * Track sale as conversion
     */
    public function trackSaleConversion(Campaign $campaign, \App\Models\Sale $sale): void
    {
        $this->recordConversion->execute(
            $campaign,
            ConversionType::PURCHASE,
            $sale->total_amount,
            $sale,
            $sale->customer_id
        );
    }

    /**
     * Find campaign by promo code
     */
    public function findByPromoCode(string $code): ?Campaign
    {
        return Campaign::active()
            ->withPromoCode($code)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();
    }

    /**
     * Get campaign performance summary
     */
    public function getPerformanceSummary(Campaign $campaign): array
    {
        return [
            'roi' => $campaign->roi,
            'roas' => $campaign->roas,
            'ctr' => $campaign->ctr,
            'conversion_rate' => $campaign->conversion_rate,
            'cpc' => $campaign->cpc,
            'cpa' => $campaign->cpa,
            'budget_utilization' => $campaign->budget_utilization,
            'revenue_progress' => $campaign->revenue_progress,
            'days_remaining' => $campaign->days_remaining,
        ];
    }

    /**
     * Get ROI report for all campaigns
     */
    public function getRoiReport(?Shop $shop = null, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->subYear();
        $endDate = $endDate ?? now();

        return Campaign::with(['shop'])
            ->whereIn('status', [CampaignStatus::ACTIVE, CampaignStatus::COMPLETED])
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)
            ->get()
            ->map(fn ($campaign) => [
                'campaign' => $campaign,
                'spent' => $campaign->spent,
                'revenue' => $campaign->actual_revenue,
                'profit' => $campaign->actual_revenue - $campaign->spent,
                'roi' => $campaign->roi,
                'roas' => $campaign->roas,
            ])
            ->sortByDesc('roi');
    }

    /**
     * Get performance by channel
     */
    public function getPerformanceByChannel(?Shop $shop = null, ?Carbon $startDate = null, ?Carbon $endDate = null): Collection
    {
        $startDate = $startDate ?? now()->subMonths(6);
        $endDate = $endDate ?? now();

        return Campaign::query()
            ->whereIn('status', [CampaignStatus::ACTIVE, CampaignStatus::COMPLETED])
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->where('start_date', '>=', $startDate)
            ->where('end_date', '<=', $endDate)
            ->get()
            ->groupBy('channel')
            ->map(function ($campaigns, $channel) {
                $totalSpent = $campaigns->sum('spent');
                $totalRevenue = $campaigns->sum('actual_revenue');
                
                return [
                    'channel' => MarketingChannel::from($channel),
                    'campaign_count' => $campaigns->count(),
                    'total_spent' => $totalSpent,
                    'total_revenue' => $totalRevenue,
                    'total_conversions' => $campaigns->sum('conversions'),
                    'avg_roi' => $totalSpent > 0 ? (($totalRevenue - $totalSpent) / $totalSpent) * 100 : 0,
                    'avg_roas' => $totalSpent > 0 ? $totalRevenue / $totalSpent : 0,
                ];
            })
            ->sortByDesc('avg_roi');
    }

    /**
     * Get performance by type
     */
    public function getPerformanceByType(?Shop $shop = null): Collection
    {
        return Campaign::query()
            ->whereIn('status', [CampaignStatus::ACTIVE, CampaignStatus::COMPLETED])
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->get()
            ->groupBy('campaign_type')
            ->map(function ($campaigns, $type) {
                $totalSpent = $campaigns->sum('spent');
                $totalRevenue = $campaigns->sum('actual_revenue');
                
                return [
                    'type' => CampaignType::from($type),
                    'campaign_count' => $campaigns->count(),
                    'total_spent' => $totalSpent,
                    'total_revenue' => $totalRevenue,
                    'avg_roi' => $totalSpent > 0 ? (($totalRevenue - $totalSpent) / $totalSpent) * 100 : 0,
                ];
            });
    }

    /**
     * Get dashboard summary
     */
    public function getDashboardSummary(?Shop $shop = null): array
    {
        $activeCampaigns = Campaign::active()
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->get();

        $thisMonth = Campaign::query()
            ->whereIn('status', [CampaignStatus::ACTIVE, CampaignStatus::COMPLETED])
            ->when($shop, fn ($q) => $q->forShop($shop->id))
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now()->startOfMonth())
            ->get();

        $totalSpent = $thisMonth->sum('spent');
        $totalRevenue = $thisMonth->sum('actual_revenue');

        return [
            'active_campaigns' => $activeCampaigns->count(),
            'total_budget' => $activeCampaigns->sum('budget'),
            'total_spent' => $totalSpent,
            'total_revenue' => $totalRevenue,
            'overall_roi' => $totalSpent > 0 ? (($totalRevenue - $totalSpent) / $totalSpent) * 100 : 0,
            'overall_roas' => $totalSpent > 0 ? $totalRevenue / $totalSpent : 0,
            'total_conversions' => $thisMonth->sum('conversions'),
            'top_performing' => $activeCampaigns->sortByDesc('roi')->first(),
        ];
    }

    /**
     * Get trend data
     */
    public function getTrend(?Shop $shop = null, int $months = 6): Collection
    {
        $data = collect();

        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            
            $campaigns = Campaign::query()
                ->whereIn('status', [CampaignStatus::ACTIVE, CampaignStatus::COMPLETED])
                ->when($shop, fn ($q) => $q->forShop($shop->id))
                ->whereMonth('start_date', '<=', $date->month)
                ->whereMonth('end_date', '>=', $date->month)
                ->whereYear('start_date', '<=', $date->year)
                ->whereYear('end_date', '>=', $date->year)
                ->get();

            $spent = $campaigns->sum('spent');
            $revenue = $campaigns->sum('actual_revenue');

            $data->push([
                'month' => $date->format('M Y'),
                'spent' => $spent,
                'revenue' => $revenue,
                'roi' => $spent > 0 ? (($revenue - $spent) / $spent) * 100 : 0,
            ]);
        }

        return $data;
    }

    /**
     * Auto-complete campaigns that have ended
     */
    public function autoCompleteCampaigns(): int
    {
        $count = 0;
        
        Campaign::active()
            ->where('end_date', '<', now())
            ->each(function ($campaign) use (&$count) {
                $campaign->complete();
                $count++;
            });

        return $count;
    }

    /**
     * Auto-activate scheduled campaigns
     */
    public function autoActivateCampaigns(): int
    {
        $count = 0;
        
        Campaign::status(CampaignStatus::SCHEDULED)
            ->where('start_date', '<=', now())
            ->each(function ($campaign) use (&$count) {
                $campaign->activate();
                $count++;
            });

        return $count;
    }
}
```

---

## 7. Controllers

### CampaignController

**File:** `app/Http/Controllers/CampaignController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\MarketingChannel;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use App\Models\Shop;
use App\Services\CampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(private CampaignService $campaignService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Campaign::class);

        $shopId = $request->get('shop_id');
        $status = $request->get('status');
        $channel = $request->get('channel');

        $campaigns = Campaign::with(['shop'])
            ->when($shopId, fn ($q) => $q->forShop($shopId))
            ->when($status, fn ($q) => $q->status(CampaignStatus::from($status)))
            ->when($channel, fn ($q) => $q->channel(MarketingChannel::from($channel)))
            ->latest()
            ->paginate(20);

        $shops = Shop::active()->get();
        $summary = $this->campaignService->getDashboardSummary(
            $shopId ? Shop::find($shopId) : null
        );

        return view('campaigns.index', compact('campaigns', 'shops', 'summary'));
    }

    public function create(): View
    {
        $this->authorize('create', Campaign::class);

        $shops = Shop::active()->get();
        $types = CampaignType::cases();
        $channels = MarketingChannel::cases();

        return view('campaigns.create', compact('shops', 'types', 'channels'));
    }

    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        $campaign = $this->campaignService->create($request->validated());

        return redirect()->route('campaigns.show', $campaign->uuid)
            ->with('success', 'Campaign created successfully.');
    }

    public function show(Campaign $campaign): View
    {
        $this->authorize('view', $campaign);

        $campaign->load(['shop', 'expenses', 'conversionsRecords.sale', 'metrics']);
        $performance = $this->campaignService->getPerformanceSummary($campaign);

        return view('campaigns.show', compact('campaign', 'performance'));
    }

    public function edit(Campaign $campaign): View
    {
        $this->authorize('update', $campaign);

        $shops = Shop::active()->get();
        $types = CampaignType::cases();
        $channels = MarketingChannel::cases();

        return view('campaigns.edit', compact('campaign', 'shops', 'types', 'channels'));
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        try {
            $this->campaignService->update($campaign, $request->validated());
            return redirect()->route('campaigns.show', $campaign->uuid)
                ->with('success', 'Campaign updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        $this->authorize('delete', $campaign);

        $campaign->delete();

        return redirect()->route('campaigns.index')
            ->with('success', 'Campaign deleted successfully.');
    }

    public function activate(Campaign $campaign): RedirectResponse
    {
        $this->authorize('activate', $campaign);

        try {
            $this->campaignService->activate($campaign);
            return back()->with('success', 'Campaign activated.');
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pause(Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $campaign->pause();
        return back()->with('success', 'Campaign paused.');
    }

    public function complete(Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $campaign->complete();
        return back()->with('success', 'Campaign marked as completed.');
    }

    public function roiReport(Request $request): View
    {
        $this->authorize('reports', Campaign::class);

        $shopId = $request->get('shop_id');
        $shop = $shopId ? Shop::find($shopId) : null;

        $roiData = $this->campaignService->getRoiReport($shop);
        $byChannel = $this->campaignService->getPerformanceByChannel($shop);
        $byType = $this->campaignService->getPerformanceByType($shop);
        $trend = $this->campaignService->getTrend($shop);

        $shops = Shop::active()->get();

        return view('campaigns.roi-report', compact('roiData', 'byChannel', 'byType', 'trend', 'shops'));
    }
}
```

---

## 8. Form Requests

### StoreCampaignRequest

**File:** `app/Http/Requests/StoreCampaignRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\CampaignType;
use App\Enums\MarketingChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Campaign::class);
    }

    public function rules(): array
    {
        return [
            'shop_id' => ['nullable', 'exists:shops,id'],
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'campaign_type' => ['required', Rule::enum(CampaignType::class)],
            'channel' => ['required', Rule::enum(MarketingChannel::class)],
            'budget' => ['required', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'target_revenue' => ['nullable', 'numeric', 'min:0'],
            'target_conversions' => ['nullable', 'integer', 'min:0'],
            'target_reach' => ['nullable', 'integer', 'min:0'],
            'promo_code' => ['nullable', 'string', 'max:50', 'unique:campaigns,promo_code'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
```

---

## 9. Routes

**File:** `routes/web.php`

```php
use App\Http\Controllers\CampaignController;

Route::middleware(['auth'])->prefix('campaigns')->name('campaigns.')->group(function () {
    Route::get('/', [CampaignController::class, 'index'])
        ->name('index')
        ->middleware('permission:campaigns.view');
    
    Route::get('/roi-report', [CampaignController::class, 'roiReport'])
        ->name('roi-report')
        ->middleware('permission:campaigns.reports');
    
    Route::get('/create', [CampaignController::class, 'create'])
        ->name('create')
        ->middleware('permission:campaigns.create');
    
    Route::post('/', [CampaignController::class, 'store'])
        ->name('store')
        ->middleware('permission:campaigns.create');
    
    Route::get('/{campaign}', [CampaignController::class, 'show'])
        ->name('show')
        ->middleware('permission:campaigns.view');
    
    Route::get('/{campaign}/edit', [CampaignController::class, 'edit'])
        ->name('edit')
        ->middleware('permission:campaigns.update');
    
    Route::put('/{campaign}', [CampaignController::class, 'update'])
        ->name('update')
        ->middleware('permission:campaigns.update');
    
    Route::delete('/{campaign}', [CampaignController::class, 'destroy'])
        ->name('destroy')
        ->middleware('permission:campaigns.delete');
    
    Route::post('/{campaign}/activate', [CampaignController::class, 'activate'])
        ->name('activate')
        ->middleware('permission:campaigns.activate');
    
    Route::post('/{campaign}/pause', [CampaignController::class, 'pause'])
        ->name('pause')
        ->middleware('permission:campaigns.update');
    
    Route::post('/{campaign}/complete', [CampaignController::class, 'complete'])
        ->name('complete')
        ->middleware('permission:campaigns.update');
});
```

---

## 10. Permissions

```php
'campaigns.view',
'campaigns.create',
'campaigns.update',
'campaigns.delete',
'campaigns.activate',
'campaigns.reports',
```

---

## 11. Scheduled Commands

**File:** `app/Console/Commands/UpdateCampaignStatusCommand.php`

```php
<?php

namespace App\Console\Commands;

use App\Services\CampaignService;
use Illuminate\Console\Command;

class UpdateCampaignStatusCommand extends Command
{
    protected $signature = 'campaigns:update-status';
    protected $description = 'Auto-activate and complete campaigns based on dates';

    public function handle(CampaignService $service): int
    {
        $activated = $service->autoActivateCampaigns();
        $completed = $service->autoCompleteCampaigns();
        
        $this->info("Activated: {$activated}, Completed: {$completed}");
        return Command::SUCCESS;
    }
}
```

---

## 12. Tests

**File:** `tests/Feature/CampaignsTest.php`

```php
<?php

use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\ConversionType;
use App\Enums\MarketingChannel;
use App\Models\Campaign;
use App\Models\Sale;
use App\Services\CampaignService;

test('campaign can be created', function () {
    $service = app(CampaignService::class);
    
    $campaign = $service->create([
        'name' => 'Summer Sale',
        'campaign_type' => CampaignType::SALES->value,
        'channel' => MarketingChannel::SOCIAL_MEDIA->value,
        'budget' => 10000,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
    ]);

    expect($campaign)
        ->name->toBe('Summer Sale')
        ->status->toBe(CampaignStatus::DRAFT);
});

test('campaign code is auto-generated', function () {
    $campaign = Campaign::factory()->create();
    expect($campaign->code)->toStartWith('CMP');
});

test('roi is calculated correctly', function () {
    $campaign = Campaign::factory()->create([
        'spent' => 1000,
        'actual_revenue' => 3000,
    ]);

    expect($campaign->roi)->toBe(200.0);
    expect($campaign->roas)->toBe(3.0);
});

test('conversion tracking updates campaign totals', function () {
    $campaign = Campaign::factory()->create([
        'conversions' => 0,
        'actual_revenue' => 0,
    ]);
    
    $sale = Sale::factory()->create(['total_amount' => 500]);
    
    $service = app(CampaignService::class);
    $service->trackSaleConversion($campaign, $sale);

    expect($campaign->fresh())
        ->conversions->toBe(1)
        ->actual_revenue->toBe(500.00);
});

test('campaign can be found by promo code', function () {
    $campaign = Campaign::factory()->create([
        'promo_code' => 'SUMMER20',
        'status' => CampaignStatus::ACTIVE,
        'start_date' => now()->subDay(),
        'end_date' => now()->addMonth(),
    ]);

    $service = app(CampaignService::class);
    $found = $service->findByPromoCode('SUMMER20');

    expect($found->id)->toBe($campaign->id);
});

test('performance by channel is calculated', function () {
    Campaign::factory()->count(3)->create([
        'channel' => MarketingChannel::SOCIAL_MEDIA,
        'status' => CampaignStatus::COMPLETED,
    ]);

    $service = app(CampaignService::class);
    $byChannel = $service->getPerformanceByChannel();

    expect($byChannel)->toHaveKey(MarketingChannel::SOCIAL_MEDIA->value);
});
```

---

## 13. Verification Checklist

- [ ] All tables have `uuid` column with `getRouteKeyName()`
- [ ] All tables have audit columns
- [ ] Campaign status workflow works
- [ ] ROI/ROAS calculations accurate
- [ ] Conversion tracking updates totals
- [ ] Promo code lookup works
- [ ] Daily metrics aggregation
- [ ] All permissions follow `{module}.{action}` format
- [ ] All tests pass

---

## 14. Next Steps

→ **[Module 16: Alerts & Notifications](./16-alerts-notifications.md)**
