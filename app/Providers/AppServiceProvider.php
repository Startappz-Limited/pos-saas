<?php

namespace App\Providers;

use App\Contracts\MpesaGateway;
use App\Enums\Limit as PlanLimit;
use App\Listeners\AuditAuthorizationChanges;
use App\Models\BaileysSession;
use App\Models\BusinessMembership;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\Refund;
use App\Models\Register;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\SocialAccount;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Observers\ProductObserver;
use App\Policies\BaileysSessionPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\PurchaseReturnPolicy;
use App\Policies\RefundPolicy;
use App\Policies\ReturnPolicy;
use App\Policies\RolePolicy;
use App\Policies\SalePolicy;
use App\Policies\ShopPolicy;
use App\Policies\SocialAccountPolicy;
use App\Policies\StockAdjustmentPolicy;
use App\Services\Billing\PlanLimits;
use App\Services\Mpesa\NullMpesaGateway;
use App\Support\Onboarding\OnboardingChecklist;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantPresenceVerifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Log\Context\Repository as ContextRepository;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Until the private M-Pesa package is connected, nothing is accepted.
        $this->app->bind(MpesaGateway::class, NullMpesaGateway::class);

        // The business the current request, job or command acts for; reset
        // between requests and queued jobs (stage 1a).
        $this->app->scoped(TenantContext::class);

        // exists: and unique: rules respect the tenant scopes (stage 1a).
        $this->app->extend('validation.presence', fn ($verifier, $app) => new TenantPresenceVerifier($app['db']));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap 5 pagination views
        Paginator::useBootstrapFive();

        // Every form carries the business it was loaded for (decision 4.9):
        // `@csrf` also writes the `_business` field VerifyFormBusiness checks.
        Blade::directive('csrf', fn (): string => '<?php echo csrf_field(); echo \App\Support\Tenancy\BusinessField::html(); ?>');

        View::composer('partials.onboarding-checklist', fn ($view) => $view->with('steps', app(OnboardingChecklist::class)->steps()));

        $this->registerPlanLimits();
        $this->registerRateLimiters();
        $this->enforceHttps();
        $this->definePasswordPolicy();
        $this->carryTenantIntoQueuedWork();

        // Only the platform operator may do everything (decision 4.4). A
        // business owner holds the admin role, with every permission in their
        // business, and the policies still apply to them.
        Gate::before(function ($user, $ability) {
            return $user instanceof User && $user->isPlatformOperator() ? true : null;
        });

        // Register Policies
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Shop::class, ShopPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(StockAdjustment::class, StockAdjustmentPolicy::class);
        Gate::policy(PurchaseReturn::class, PurchaseReturnPolicy::class);
        Gate::policy(SaleReturn::class, ReturnPolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        Gate::policy(Campaign::class, CampaignPolicy::class);
        Gate::policy(SocialAccount::class, SocialAccountPolicy::class);
        Gate::policy(BaileysSession::class, BaileysSessionPolicy::class);

        // Route Model Bindings
        Route::model('return', SaleReturn::class);
        Route::model('purchase_return', PurchaseReturn::class);

        // Register Observers
        Product::observe(ProductObserver::class);

        $this->auditAuthorizationChanges();
    }

    /**
     * Role and permission grants are pivot writes, so no model event fires — hook
     * Spatie's own events instead. Auditing authorization changes is mandatory.
     */
    private function auditAuthorizationChanges(): void
    {
        Event::listen(RoleAttached::class, [AuditAuthorizationChanges::class, 'handleRoleAttached']);
        Event::listen(RoleDetached::class, [AuditAuthorizationChanges::class, 'handleRoleDetached']);
        Event::listen(PermissionAttached::class, [AuditAuthorizationChanges::class, 'handlePermissionAttached']);
        Event::listen(PermissionDetached::class, [AuditAuthorizationChanges::class, 'handlePermissionDetached']);
    }

    /**
     * Force generated URLs to https where configured (production by default), so a
     * mixed-content link can't downgrade a session.
     */
    private function enforceHttps(): void
    {
        if (config('security.force_https')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Project-wide password policy, applied wherever `Password::defaults()` is used
     * in validation rules.
     */
    private function definePasswordPolicy(): void
    {
        Password::defaults(function () {
            $rule = Password::min((int) config('security.passwords.min_length'));

            if (config('security.passwords.mixed_case')) {
                $rule->mixedCase();
            }

            if (config('security.passwords.numbers')) {
                $rule->numbers();
            }

            if (config('security.passwords.symbols')) {
                $rule->symbols();
            }

            // Network call to the HIBP range API — skipped in tests.
            if (config('security.passwords.uncompromised') && ! app()->runningUnitTests()) {
                $rule->uncompromised();
            }

            return $rule;
        });
    }

    /**
     * Named API rate limiters.
     *
     * Rate limiting is mandatory on API endpoints and on login/registration. Limits
     * live in config/ratelimit.php so they can be tuned per environment.
     */
    /**
     * Queued work carries the business it was dispatched for (stage 1c,
     * decision 4.3): jobs, queued listeners, notifications and mail. The
     * business goes into the payload's hidden context when it is queued, and
     * the worker acts for it again when the job runs (the worker resets the
     * tenant between jobs before the payload is read).
     */
    private function carryTenantIntoQueuedWork(): void
    {
        Context::dehydrating(function (ContextRepository $context): void {
            $businessId = app(TenantContext::class)->id();

            if ($businessId !== null) {
                $context->addHidden(TenantContext::CONTEXT_KEY, $businessId);
            }
        });

        Context::hydrated(function (ContextRepository $context): void {
            if ($context->hasHidden(TenantContext::CONTEXT_KEY)) {
                app(TenantContext::class)->set((int) $context->getHidden(TenantContext::CONTEXT_KEY));
            }
        });
    }

    /**
     * A plan's limits hold wherever something is created (Phase 4): the same
     * check for web, both APIs, imports and sync.
     */
    private function registerPlanLimits(): void
    {
        $limits = fn (): PlanLimits => app(PlanLimits::class);

        // These listeners run before the model's own, which fills business_id
        // from the current business, so fall back to it here.
        $businessOf = fn ($model): int => (int) ($model->business_id ?: app(TenantContext::class)->id());

        Shop::creating(function (Shop $shop) use ($limits, $businessOf): void {
            if ($businessOf($shop) > 0) {
                $limits()->assertCanAdd(PlanLimit::Shops, $businessOf($shop));
            }
        });
        Register::creating(fn (Register $register) => $limits()->assertCanAddForShop(PlanLimit::Registers, (int) $register->shop_id));
        BaileysSession::creating(fn (BaileysSession $session) => $limits()->assertCanAddForShop(PlanLimit::WhatsappSessions, (int) $session->shop_id));
        Product::creating(function (Product $product) use ($limits, $businessOf): void {
            if ($businessOf($product) > 0) {
                $limits()->assertCanAdd(PlanLimit::Products, $businessOf($product));
            }
        });
        BusinessMembership::creating(function (BusinessMembership $membership) use ($limits): void {
            if ($membership->status === BusinessMembership::STATUS_ACTIVE) {
                $limits()->assertCanAdd(PlanLimit::Staff, (int) $membership->business_id);
            }
        });
    }

    private function registerRateLimiters(): void
    {
        // General authenticated traffic: per user where we know them, else per IP.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(config('ratelimit.api'))
            ->by($request->user()?->id ?: $request->ip()));

        // Auth endpoints: keyed by IP *and* the submitted email, so hammering one
        // account cannot lock out an unrelated user on a shared IP.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(config('ratelimit.auth'))->by('auth-ip:'.$request->ip()),
            Limit::perMinute(config('ratelimit.auth'))
                ->by('auth-email:'.Str::lower((string) $request->input('email'))),
        ]);

        // Inbound platform webhooks are public, so key per shop from the route
        // parameter and fall back to IP for bridges that post without one.
        RateLimiter::for('webhooks', function (Request $request) {
            $shop = $request->route('shop');

            return Limit::perMinute(config('ratelimit.webhooks'))
                ->by('webhook:'.($shop instanceof Shop ? $shop->getKey() : ($shop ?: $request->ip())));
        });

        // Chatway Gateway's single-source WhatsApp firehose (see config/ratelimit.php).
        RateLimiter::for('wa-gateway-webhook', fn (Request $request) => Limit::perMinute(config('ratelimit.wa_gateway_webhooks'))
            ->by('wa-gateway-webhook:'.$request->ip()));

        // Creating money-affecting records.
        RateLimiter::for('writes', fn (Request $request) => Limit::perMinute(config('ratelimit.writes'))
            ->by($request->user()?->id ?: $request->ip()));

        // Destructive or irreversible actions.
        RateLimiter::for('destructive', fn (Request $request) => Limit::perMinute(config('ratelimit.destructive'))
            ->by($request->user()?->id ?: $request->ip()));
    }
}
