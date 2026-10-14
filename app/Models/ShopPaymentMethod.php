<?php

namespace App\Models;

use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

class ShopPaymentMethod extends Model
{
    use BelongsToShop;
    //
}
