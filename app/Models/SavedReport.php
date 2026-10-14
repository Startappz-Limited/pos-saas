<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\BelongsToShop;
use Illuminate\Database\Eloquent\Model;

class SavedReport extends Model
{
    use BelongsToShop;

    /** Rows with no shop apply to the whole business. */
    protected static bool $shopOptional = true;

    use BelongsToBusiness;
    //
}
