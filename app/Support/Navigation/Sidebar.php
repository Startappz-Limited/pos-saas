<?php

namespace App\Support\Navigation;

use App\Models\AbandonedCart;
use App\Models\Alert;
use App\Models\Attribute;
use App\Models\AuditLog;
use App\Models\BaileysSession;
use App\Models\BusinessInvite;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\CreditAccount;
use App\Models\CreditTransaction;
use App\Models\Customer;
use App\Models\EcommerceOrder;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventorySnapshot;
use App\Models\LowStockAlert;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Refund;
use App\Models\Role;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\SocialAccount;
use App\Models\StockAdjustment;
use App\Models\StockIntake;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Closure;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * The business sidebar, defined once and filtered for the signed-in user.
 *
 * Every link is shown only when its page would let the user in (the same
 * policy or permission the page checks), a group only when one of its links
 * is shown, and a section title only when something under it is. Adding a
 * page to the menu is one entry here; the partial only renders the result.
 *
 * Creating a record is reached from its list page (each has an Add button),
 * so the menu lists places, not actions.
 */
final class Sidebar
{
    /**
     * The menu for $user: sections with their visible items.
     *
     * @return list<array{title: string, items: list<array<string, mixed>>}>
     */
    public static function for(User $user): array
    {
        $sections = [];

        foreach (self::definition() as $section) {
            $items = [];

            foreach ($section['items'] as $item) {
                if (isset($item['children'])) {
                    $children = array_values(array_filter($item['children'], fn (array $child): bool => self::allows($user, $child)));

                    if ($children === [] || ! self::allows($user, $item)) {
                        continue;
                    }

                    $items[] = [...$item, 'children' => $children, 'active' => self::anyActive($children)];

                    continue;
                }

                if (self::allows($user, $item)) {
                    $items[] = [...$item, 'active' => request()->routeIs(...$item['active'])];
                }
            }

            if ($items !== []) {
                $sections[] = ['title' => $section['title'], 'items' => $items];
            }
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function allows(User $user, array $item): bool
    {
        if (! isset($item['can'])) {
            return true;
        }

        // Some policies call hasPermissionTo(), which throws for a permission
        // that isn't seeded; the menu treats that as "not allowed" rather than
        // breaking the page.
        try {
            return ($item['can'])($user);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $children
     */
    private static function anyActive(array $children): bool
    {
        foreach ($children as $child) {
            if (request()->routeIs(...$child['active'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * A user may open the list of $model (its policy's viewAny).
     *
     * @param  class-string  $model
     */
    private static function canList(string $model): Closure
    {
        return fn (User $user): bool => $user->can('viewAny', $model);
    }

    /**
     * @return Closure(User): bool
     */
    private static function holds(string ...$permissions): Closure
    {
        return function (User $user) use ($permissions): bool {
            foreach ($permissions as $permission) {
                if ($user->can($permission)) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * @param  list<string>|null  $active  route name patterns that mark it current (default: the route)
     * @return array<string, mixed>
     */
    private static function link(string $label, string $route, ?Closure $can = null, ?array $active = null, ?string $icon = null): array
    {
        return array_filter([
            'label' => $label,
            'route' => $route,
            'can' => $can,
            'active' => $active ?? [$route],
            'icon' => $icon,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private static function group(string $key, string $label, string $icon, array $children, ?Closure $can = null): array
    {
        return array_filter(['key' => $key, 'label' => $label, 'icon' => $icon, 'children' => $children, 'can' => $can], fn ($value) => $value !== null);
    }

    /**
     * @return list<array{title: string, items: list<array<string, mixed>>}>
     */
    private static function definition(): array
    {
        return [
            ['title' => __('General'), 'items' => [
                self::link(__('Dashboard'), 'dashboard', icon: 'solar:widget-5-bold-duotone'),
                self::link(__('Shops'), 'shops.index', self::canList(Shop::class), ['shops.*'], 'solar:shop-2-bold-duotone'),
                self::link(__('Alerts'), 'alerts.index', self::canList(Alert::class), ['alerts.*'], 'solar:bell-bold-duotone'),
                self::link(__('Calendar'), 'calendar.index', self::holds('alerts.view'), ['calendar.*'], 'solar:calendar-bold-duotone'),
            ]],

            ['title' => __('Catalogue'), 'items' => [
                self::group('products', __('Products'), 'solar:t-shirt-bold-duotone', [
                    self::link(__('All products'), 'products.index', self::canList(Product::class), ['products.index', 'products.create', 'products.show', 'products.edit', 'products.import.*']),
                    self::link(__('Low stock'), 'products.lowStock', self::canList(Product::class)),
                    self::link(__('Purchase costs'), 'products.purchase-costs.index', fn (User $user): bool => $user->can('setCost', Product::class), ['products.purchase-costs.*']),
                    self::link(__('Pricing rules'), 'pricing-rules.index', self::canList(PricingRule::class), ['pricing-rules.*']),
                ]),
                self::link(__('Categories'), 'categories.index', self::canList(Category::class), ['categories.*'], 'solar:folder-with-files-bold-duotone'),
                self::link(__('Attributes'), 'attributes.index', self::canList(Attribute::class), ['attributes.*'], 'solar:tag-bold-duotone'),
            ]],

            ['title' => __('Stock'), 'items' => [
                self::group('stock', __('Stock'), 'solar:box-bold-duotone', [
                    self::link(__('Stock intake'), 'stock-intakes.index', self::canList(StockIntake::class), ['stock-intakes.*']),
                    self::link(__('Adjustments'), 'stock-adjustments.index', self::canList(StockAdjustment::class), ['stock-adjustments.*']),
                    self::link(__('Movements'), 'stock-movements.index', self::canList(StockMovement::class), ['stock-movements.*']),
                    self::link(__('Snapshots'), 'inventory-snapshots.index', self::canList(InventorySnapshot::class), ['inventory-snapshots.*']),
                    self::link(__('Low-stock alerts'), 'low-stock-alerts.index', self::canList(LowStockAlert::class), ['low-stock-alerts.*']),
                ], self::holds('stock.view')),
                self::group('purchasing', __('Purchasing'), 'solar:card-send-bold-duotone', [
                    self::link(__('Purchase orders'), 'purchase-orders.index', self::canList(PurchaseOrder::class), ['purchase-orders.*']),
                    self::link(__('Supplier returns'), 'purchase-returns.index', self::canList(PurchaseReturn::class), ['purchase-returns.*']),
                ]),
                self::link(__('Suppliers'), 'suppliers.index', self::canList(Supplier::class), ['suppliers.*'], 'solar:delivery-bold-duotone'),
            ]],

            ['title' => __('Sales'), 'items' => [
                self::group('sales', __('Sales'), 'solar:bag-smile-bold-duotone', [
                    self::link(__('All sales'), 'sales.index', self::holds('sales.view'), ['sales.index', 'sales.show', 'sales.edit', 'sales.create']),
                    self::link(__('Payments'), 'payments.index', self::canList(SalePayment::class), ['payments.*']),
                    self::link(__('Returns'), 'returns.index', self::canList(SaleReturn::class), ['returns.*']),
                    self::link(__('Refunds'), 'refunds.index', self::canList(Refund::class), ['refunds.*']),
                    self::link(__('Website orders'), 'ecommerce-orders.index', self::canList(EcommerceOrder::class), ['ecommerce-orders.*']),
                    self::link(__('Abandoned carts'), 'abandoned-carts.index', self::canList(AbandonedCart::class), ['abandoned-carts.*']),
                ]),
                self::link(__('Customers'), 'customers.index', self::canList(Customer::class), ['customers.*'], 'solar:users-group-two-rounded-bold-duotone'),
                self::link(__('Till sync review'), 'sync-issues.index', self::holds('sync-issues.view'), ['sync-issues.*'], 'solar:cloud-check-bold-duotone'),
            ]],

            ['title' => __('Finance'), 'items' => [
                // Open to every member: the till opens a session here.
                self::link(__('Cash registers'), 'cash-registers.index', null, ['cash-registers.*'], 'solar:wallet-money-bold-duotone'),
                self::group('credit', __('Credit'), 'solar:card-2-bold-duotone', [
                    self::link(__('Credit accounts'), 'credit-accounts.index', self::canList(CreditAccount::class), ['credit-accounts.*']),
                    self::link(__('Transactions'), 'credit-transactions.index', self::canList(CreditTransaction::class), ['credit-transactions.*']),
                ]),
                self::group('expenses', __('Expenses'), 'solar:bill-list-bold-duotone', [
                    self::link(__('All expenses'), 'expenses.index', self::canList(Expense::class), ['expenses.*']),
                    self::link(__('Expense categories'), 'expense-categories.index', self::canList(ExpenseCategory::class), ['expense-categories.*']),
                ]),
            ]],

            ['title' => __('Marketing'), 'items' => [
                self::group('campaigns', __('Campaigns'), 'solar:leaf-bold-duotone', [
                    self::link(__('All campaigns'), 'campaigns.index', self::canList(Campaign::class), ['campaigns.*']),
                    self::link(__('Social accounts'), 'social-accounts.index', self::canList(SocialAccount::class), ['social-accounts.*']),
                ]),
                self::group('whatsapp', __('WhatsApp'), 'solar:chat-round-line-bold-duotone', [
                    self::link(__('Sessions'), 'baileys.sessions.index', self::canList(BaileysSession::class), ['baileys.sessions.*']),
                    self::link(__('Inbox'), 'baileys.inbox.index', self::canList(BaileysSession::class), ['baileys.inbox.*']),
                    self::link(__('Broadcast'), 'baileys.broadcast.show', self::canList(BaileysSession::class), ['baileys.broadcast.*']),
                ]),
            ]],

            ['title' => __('Reports'), 'items' => [
                self::group('reports', __('Reports'), 'solar:chart-2-bold-duotone', [
                    self::link(__('All reports'), 'reports.index', self::holds('reports.view'), ['reports.index', 'reports.sales*', 'reports.inventory*', 'reports.expenses*', 'reports.profit*']),
                    self::link(__(':tax summary', ['tax' => config('tax.label', 'VAT')]), 'reports.vat', self::holds('reports.view'), ['reports.vat*']),
                ]),
            ]],

            ['title' => __('People'), 'items' => [
                self::link(__('Users'), 'users.index', self::canList(User::class), ['users.*'], 'solar:users-group-rounded-bold-duotone'),
                self::link(__('Invitations'), 'invites.index', self::canList(BusinessInvite::class), ['invites.*'], 'solar:letter-unread-bold-duotone'),
                self::link(__('Roles'), 'roles.index', self::canList(Role::class), ['roles.*'], 'solar:shield-user-bold-duotone'),
            ]],

            ['title' => __('Business'), 'items' => [
                self::link(__('Business settings'), 'business.settings.edit', self::holds('settings.update', 'settings.full-access'), ['business.settings.*'], 'solar:settings-bold-duotone'),
                self::link(__('Billing'), 'billing.show', fn (User $user): bool => $user->isBusinessOwner(), ['billing.*'], 'solar:card-recive-bold-duotone'),
                self::link(__('Audit log'), 'audit-logs.index', self::canList(AuditLog::class), ['audit-logs.*'], 'solar:clipboard-list-bold-duotone'),
            ]],
        ];
    }
}
