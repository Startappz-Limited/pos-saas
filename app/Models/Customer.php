<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAccessibleShop;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Customer extends Model
{
    use BelongsToAccessibleShop;
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'shop_id',
        'code',
        'name',
        'email',
        'tax_pin',
        'phone',
        'phone_normalized',
        'address',
        'customer_type',
        'allow_credit',
        'credit_limit',
        'credit_balance',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'allow_credit' => 'boolean',
        'credit_limit' => 'decimal:2',
        'credit_balance' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Keep the canonical MSISDN in step with whatever was typed into `phone`.
     *
     * `phone` deliberately preserves the cashier's formatting because that is
     * what staff recognise on screen; `phone_normalized` is the key used to match
     * a customer across WhatsApp, SMS and mobile money.
     */
    public function setPhoneAttribute(?string $value): void
    {
        $this->attributes['phone'] = $value;
        $this->attributes['phone_normalized'] = PhoneNumber::normalize($value);
    }

    /**
     * The number in E.164 form, for anything that needs the leading plus.
     */
    public function getPhoneE164Attribute(): ?string
    {
        return PhoneNumber::toE164($this->phone_normalized ?? $this->phone);
    }

    /**
     * Find a customer by any spelling of their phone number.
     */
    public function scopeWherePhone(Builder $query, ?string $phone): Builder
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            // Nothing dialable to match on — return no rows rather than every row.
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->qualifyColumn('phone_normalized'), $normalized);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function creditAccounts(): HasMany
    {
        return $this->hasMany(CreditAccount::class);
    }

    public function creditAccount(): HasOne
    {
        return $this->hasOne(CreditAccount::class)->latestOfMany();
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    /**
     * Route notifications for the WhatsApp channel.
     */
    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->phone;
    }
}
