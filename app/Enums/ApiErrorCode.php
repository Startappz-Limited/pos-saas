<?php

namespace App\Enums;

/**
 * The `code` an API refusal carries (decision 4.8): one registry, so a client
 * can act on the reason without reading the message. The flat API sends the
 * codes it always has; every `/api/v1` error carries one (ShapeApiV1Response
 * fills the generic ones from the HTTP status).
 *
 * Codes are part of the contract: add cases, never rename or remove one.
 */
enum ApiErrorCode: string
{
    // Generic, from the HTTP status.
    case Unauthenticated = 'unauthenticated';
    case Forbidden = 'forbidden';
    case NotFound = 'not_found';
    case MethodNotAllowed = 'method_not_allowed';
    case Conflict = 'conflict';
    case ValidationFailed = 'validation_failed';
    case TooManyRequests = 'too_many_requests';
    case ServerError = 'server_error';

    // Account and business.
    case BusinessTokenRequired = 'business_token_required';
    case RegisterTokenRequired = 'register_token_required';
    case AccountInactive = 'account_inactive';
    case EmailVerificationRequired = 'email_verification_required';
    case BusinessRequired = 'business_required';
    case NoActiveBusiness = 'no_active_business';
    case BusinessSuspended = 'business_suspended';
    case PlatformOperator = 'platform_operator';

    // Billing (Phase 4).
    case SubscriptionInactive = 'subscription_inactive';
    case PlanLimitReached = 'plan_limit_reached';
    case FeatureUnavailable = 'feature_unavailable';
    case AiCreditExhausted = 'ai_credit_exhausted';

    /**
     * The code for a response that carries none of its own.
     */
    public static function forStatus(int $status): self
    {
        return match (true) {
            $status === 401 => self::Unauthenticated,
            $status === 402 => self::SubscriptionInactive,
            $status === 403 => self::Forbidden,
            $status === 404 => self::NotFound,
            $status === 405 => self::MethodNotAllowed,
            $status === 409 => self::Conflict,
            $status === 422 => self::ValidationFailed,
            $status === 429 => self::TooManyRequests,
            default => self::ServerError,
        };
    }

    /**
     * What it means, for the OpenAPI document and client developers.
     */
    public function description(): string
    {
        return match ($this) {
            self::Unauthenticated => 'No valid token was sent; sign in again.',
            self::Forbidden => 'The signed-in user may not do this.',
            self::NotFound => 'No such record in this business.',
            self::MethodNotAllowed => 'The endpoint does not accept this HTTP method.',
            self::Conflict => 'The request conflicts with the current state.',
            self::ValidationFailed => 'Some fields are invalid; see `errors`.',
            self::TooManyRequests => 'Rate limit reached; retry after the Retry-After header.',
            self::ServerError => 'Something went wrong on the server.',
            self::BusinessTokenRequired => 'This endpoint needs a token from /api/v1 sign-in, bound to one business.',
            self::RegisterTokenRequired => 'This endpoint needs a token bound to a till: pair the device, or sign in on a paired till.',
            self::AccountInactive => 'The user\'s account or membership is switched off.',
            self::EmailVerificationRequired => 'The owner must verify their email address before going on.',
            self::BusinessRequired => 'Choose a business to work in.',
            self::NoActiveBusiness => 'The user is not a member of any active business.',
            self::BusinessSuspended => 'The platform has suspended this business.',
            self::PlatformOperator => 'Platform operators work in the platform console, not in a business.',
            self::SubscriptionInactive => 'The subscription has lapsed or the business is over its shops; it is read-only.',
            self::PlanLimitReached => 'The plan allows no more of this (see `limit` and `allowed`).',
            self::FeatureUnavailable => 'The plan does not include this feature.',
            self::AiCreditExhausted => 'The business has no AI credit left.',
        };
    }
}
