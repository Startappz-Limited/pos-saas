<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * Links to customer documents (invoices, statements) that open without
 * signing in. They are signed and expire (stage 1c): a link that never
 * expired would expose the document to anyone it was ever forwarded to.
 * The lifetime is config('invoicing.document_link_days').
 */
class DocumentLinks
{
    /**
     * @param  mixed  $parameters  route parameters, as for route()
     */
    public static function signed(string $route, mixed $parameters): string
    {
        return URL::temporarySignedRoute($route, now()->addDays(self::days()), $parameters);
    }

    public static function days(): int
    {
        return max(1, (int) config('invoicing.document_link_days', 30));
    }
}
