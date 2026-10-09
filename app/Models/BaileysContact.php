<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaileysContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'baileys_session_id',
        'shop_id',
        'customer_id',
        'jid',
        'phone',
        'name',
        'push_name',
        'profile_picture_url',
        'is_business',
        'is_blocked',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_business' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(BaileysSession::class, 'baileys_session_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
