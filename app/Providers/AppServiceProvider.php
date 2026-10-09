<?php

namespace App\Providers;

use App\Listeners\AuditAuthorizationChanges;
use App\Models\BaileysSession;
use App\Models\Business;
use App\Models\BusinessExport;
use App\Models\Campaign;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\Refund;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\SocialAccount;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Observers\ProductObserver;
use App\Policies\BaileysSessionPolicy;
use App\Policies\BusinessExportPolicy;
use App\Policies\BusinessPolicy;
use App\Policies\CampaignPolicy;
use App\Policies\PurchaseReturnPolicy;
use App\Policies\RefundPolicy;
use App\Policies\ReturnPolicy;
use App\Policies\RolePolicy;
use App\Policies\SalePolicy;
use App\Policies\ShopPolicy;
use App\Policies\SocialAccountPolicy;
use App\Policies\StockAdjustmentPolicy;
use App\Support\FullAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;
use Spatie\Permission\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap 5 pagination views
        Paginator::useBootstrapFive();

        $this->registerRateLimiters();
        $this->enforceHttps();
        $this->definePasswordPolicy();

        // Super-admin bypasses all permission checks, except deleting a user:
        // a platform operator deactivates or suspends accounts, and removing
        // someone is left to the admins of their business
        Gate::before(function ($user, $ability, $arguments = []) {
            if (! $user->hasRole('super-admin')) {
                return null;
            }

            if (in_array($ability, ['delete', 'forceDelete'], true) && ($arguments[0] ?? null) instanceof User) {
                return false;
            }

            // Exporting and closing a business are for its owner only
            if (($arguments[0] ?? null) instanceof Business || ($arguments[0] ?? null) instanceof BusinessExport) {
                return null;
            }

            return true;
        });

        // While a policy decides for a {resource}.full-access holder, that
        // resource's permission checks pass (see HandlesFullAccess)
        Gate::before(fn ($user, string $ability) => FullAccess::grants($user, $ability) ? true : null);

        // Admins (shop owners) and super-admins hold every permission. Grant
        // each new permission to them as it is created, so neither role
        // silently lacks a new feature until RoleSeeder is re-run.
        Permission::created(function (Permission $permission): void {
            foreach ([Role::SUPER_ADMIN, Role::ADMIN] as $name) {
                Role::query()->whereNull('business_id')->where('name', $name)->first()?->givePermissionTo($permission);
            }
        });

        // Register Policies
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Business::class, BusinessPolicy::class);
        Gate::policy(BusinessExport::class, BusinessExportPolicy::class);
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
