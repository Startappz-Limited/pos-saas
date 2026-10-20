<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Facades\URL;

/**
 * A link into the app that belongs to one business (stage 3): opening it
 * switches the reader's session to that business first, so a link in an email
 * about business B works for someone who was last working in business A. The
 * link is signed, so neither the business nor the path can be changed.
 */
class BusinessLink
{
    /**
     * @param  string  $path  an application path such as "/ecommerce-orders/{uuid}"
     */
    public static function to(string $path): string
    {
        $business = current_business();

        if ($business === null) {
            return url($path);
        }

        return URL::signedRoute('businesses.open', ['business' => $business->uuid, 'to' => '/'.ltrim($path, '/')]);
    }

    /**
     * Whether $path is a safe place to send someone after switching: a path
     * on this site, never another host.
     */
    public static function isLocalPath(string $path): bool
    {
        return str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\') && ! preg_match('/[\r\n]/', $path);
    }
}
