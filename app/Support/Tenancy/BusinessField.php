<?php

namespace App\Support\Tenancy;

use App\Http\Middleware\VerifyFormBusiness;
use Illuminate\Support\HtmlString;

/**
 * The hidden field that ties a rendered form to the business it was loaded for
 * (decision 4.9); checked by VerifyFormBusiness.
 */
class BusinessField
{
    public static function html(): HtmlString
    {
        $business = app(TenantContext::class)->business();

        return new HtmlString($business === null ? '' : '<input type="hidden" name="'.VerifyFormBusiness::FIELD.'" value="'.e($business->uuid).'">');
    }
}
