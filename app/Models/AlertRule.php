<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

class AlertRule extends Model
{
    use BelongsToShop;

    /** Rows with no shop apply to the whole business. */
    protected static bool $shopOptional = true;

    use BelongsToBusiness;
    //
}
