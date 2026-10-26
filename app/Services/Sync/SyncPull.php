<?php

namespace App\Services\Sync;

use App\Models\Category;
use App\Models\Customer;
use App\Models\PricingRule;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Register;
use App\Models\ShopVariation;
use App\Models\SyncDeletion;
use App\Models\TillPin;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The till's sync pull (Phase 7, decision 4.5): what changed in its business
 * since its cursor, read-only offline.
 *
 * - Paged entities, each in `(updated_at, id)` order and at most `$limit` rows
 *   a pull: categories, products, variations, pricing rules, customers, and
 *   the stock and shop prices of the till's own shop.
 * - Deletions (deletes and soft deletes) from `sync_deletions`, in the same
 *   way. The till drops these first, then applies the changed rows: a deleted
 *   record is never among them, and a restored one comes back as changed.
 * - Snapshots, sent whole whenever their content differs from what the till
 *   last had: the staff who can work at this till (with their till PIN's
 *   hash, for signing in offline), and the business, shop and register
 *   settings.
 *
 * Rows are read up to a second ago, so one written later in the same second
 * is never skipped: the next pull sends it. Every query goes through the
 * models' tenant scopes, and a till's token reaches its own shop only.
 */
class SyncPull
{
    /** The paged entities, in the order the till should apply them. */
    public const ENTITIES = ['categories', 'products', 'variations', 'pricing_rules', 'customers', 'stock'];

    public function __construct(private TenantContext $tenant) {}

    /**
     * @return array{data: array<string, mixed>, cursor: SyncCursor, has_more: bool}
     */
    public function pull(Register $register, SyncCursor $cursor, int $limit): array
    {
        $horizon = Carbon::now()->subSecond()->format('Y-m-d H:i:s');
        $next = new SyncCursor($cursor->positions, $cursor->fingerprints);
        $data = [];
        $hasMore = false;

        foreach (self::ENTITIES as $entity) {
            [$query, $present] = $this->entity($entity, $register);
            [$rows, $more, $position] = $this->page($query, $cursor->position($entity), $horizon, $limit, 'updated_at');

            $data[$entity] = $rows->map($present)->values()->all();
            $hasMore = $hasMore || $more;
            if ($position !== null) {
                $next->positions[$entity] = $position;
            }
        }

        [$deletions, $more, $position] = $this->page(SyncDeletion::query(), $cursor->position('deletions'), $horizon, $limit, 'deleted_at');
        $data['deletions'] = $deletions->map(fn (SyncDeletion $deletion): array => [
            'entity' => $deletion->entity,
            'uuid' => $deletion->entity_uuid,
            'id' => (int) $deletion->entity_id,
            'deleted_at' => $deletion->deleted_at?->toIso8601String(),
        ])->values()->all();
        $hasMore = $hasMore || $more;
        if ($position !== null) {
            $next->positions['deletions'] = $position;
        }

        foreach (['staff' => $this->staff($register), 'settings' => $this->settings($register)] as $name => $snapshot) {
            $fingerprint = hash('sha256', (string) json_encode($snapshot));
            $data[$name] = ($cursor->fingerprints[$name] ?? null) === $fingerprint ? null : $snapshot;
            $next->fingerprints[$name] = $fingerprint;
        }

        return ['data' => $data, 'cursor' => $next, 'has_more' => $hasMore];
    }

    /**
     * One page of $query after $position, up to $horizon, in (time, id) order.
     *
     * @param  array{0: string, 1: int}|null  $position
     * @return array{0: Collection<int, Model>, 1: bool, 2: array{0: string, 1: int}|null}
     */
    private function page(Builder $query, ?array $position, string $horizon, int $limit, string $column): array
    {
        $table = $query->getModel()->getTable();
        $time = "COALESCE({$table}.{$column}, '1970-01-01 00:00:00')";

        $rows = $query
            ->whereRaw("{$time} <= ?", [$horizon])
            ->when($position, fn (Builder $q) => $q->where(fn (Builder $after) => $after
                ->whereRaw("{$time} > ?", [$position[0]])
                ->orWhere(fn (Builder $same) => $same->whereRaw("{$time} = ?", [$position[0]])->where("{$table}.id", '>', $position[1]))))
            ->orderByRaw($time)
            ->orderBy("{$table}.id")
            ->limit($limit + 1)
            ->get();

        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $last = $rows->last();

        $lastPosition = $last === null ? null : [
            $last->getRawOriginal($column) === null ? '1970-01-01 00:00:00' : Carbon::parse($last->getRawOriginal($column))->format('Y-m-d H:i:s'),
            (int) $last->getKey(),
        ];

        return [$rows, $more, $lastPosition];
    }

    /**
     * @return array{0: Builder, 1: Closure(Model): array<string, mixed>}
     */
    private function entity(string $entity, Register $register): array
    {
        return match ($entity) {
            'categories' => [Category::query(), fn (Category $category): array => [
                'id' => $category->id,
                'uuid' => $category->uuid,
                'name' => $category->name,
                'parent_id' => $category->parent_id,
                'status' => $category->status,
                'order' => $category->order,
                'updated_at' => $category->updated_at?->toIso8601String(),
            ]],
            'products' => [Product::query()->with('category:id,uuid'), fn (Product $product): array => [
                'id' => $product->id,
                'uuid' => $product->uuid,
                'name' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'category_uuid' => $product->category?->uuid,
                'selling_price' => self::money($product->selling_price),
                'wholesale_price' => self::money($product->wholesale_price),
                'tax_class' => self::plain($product->tax_class),
                'unit' => $product->unit,
                'track_stock' => (bool) $product->track_stock,
                'has_variations' => (bool) $product->has_variations,
                'image_url' => TenantFiles::publicUrl($product->image),
                'images' => array_values(array_filter(array_map(fn ($image) => TenantFiles::publicUrl(is_string($image) ? $image : null), (array) $product->images))),
                'status' => self::plain($product->status),
                'updated_at' => $product->updated_at?->toIso8601String(),
            ]],
            'variations' => [ProductVariation::query()->with('product:id,uuid'), fn (ProductVariation $variation): array => [
                'id' => $variation->id,
                'uuid' => $variation->uuid,
                'product_uuid' => $variation->product?->uuid,
                'name' => $variation->name,
                'sku' => $variation->sku,
                'barcode' => $variation->barcode,
                'attributes' => $variation->getAttribute('attributes'),
                'image_url' => TenantFiles::publicUrl($variation->image),
                'selling_price' => self::money($variation->selling_price),
                'wholesale_price' => self::money($variation->wholesale_price),
                'tax_class' => self::plain($variation->tax_class),
                'status' => self::plain($variation->status),
                'updated_at' => $variation->updated_at?->toIso8601String(),
            ]],
            'pricing_rules' => [PricingRule::query()->with(['product:id,uuid', 'customer:id,uuid']), fn (PricingRule $rule): array => [
                'id' => $rule->id,
                'uuid' => $rule->uuid,
                'name' => $rule->name,
                'type' => self::plain($rule->type),
                'product_uuid' => $rule->product?->uuid,
                'customer_uuid' => $rule->customer?->uuid,
                'price' => self::money($rule->price),
                'discount_percentage' => $rule->discount_percentage === null ? null : (string) $rule->discount_percentage,
                'discount_amount' => self::money($rule->discount_amount),
                'min_quantity' => $rule->min_quantity,
                'max_quantity' => $rule->max_quantity,
                'start_date' => $rule->start_date?->toIso8601String(),
                'end_date' => $rule->end_date?->toIso8601String(),
                'priority' => $rule->priority,
                'is_active' => (bool) $rule->is_active,
                'updated_at' => $rule->updated_at?->toIso8601String(),
            ]],
            'customers' => [Customer::query(), fn (Customer $customer): array => [
                'id' => $customer->id,
                'uuid' => $customer->uuid,
                'code' => $customer->code,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'tax_pin' => $customer->tax_pin,
                'customer_type' => $customer->customer_type,
                'allow_credit' => (bool) $customer->allow_credit,
                'credit_limit' => self::money($customer->credit_limit),
                'status' => $customer->status,
                'updated_at' => $customer->updated_at?->toIso8601String(),
            ]],
            'stock' => [ShopVariation::query()->where('shop_variation.shop_id', $register->shop_id)->with(['product:id,uuid', 'variation:id,uuid,is_default']), fn (ShopVariation $stock): array => [
                'product_uuid' => $stock->product?->uuid,
                // The hidden default variation of a product without variations is null.
                'variation_uuid' => $stock->variation?->is_default ? null : $stock->variation?->uuid,
                'quantity' => (int) $stock->stock_quantity,
                'reorder_level' => $stock->reorder_level,
                'selling_price' => self::money($stock->selling_price),
                'wholesale_price' => self::money($stock->wholesale_price),
                'is_active' => (bool) $stock->is_active,
                'updated_at' => $stock->updated_at?->toIso8601String(),
            ]],
        };
    }

    /**
     * Who can work at this till: the business's members who may reach its
     * shop, with their roles and permissions here, so the till can check
     * them offline.
     *
     * @return list<array<string, mixed>>
     */
    private function staff(Register $register): array
    {
        $pins = TillPin::query()->get()->keyBy('user_id');

        return User::query()->inCurrentBusiness()->withBusinessStatus()->orderBy('users.id')->get()
            ->filter(fn (User $user): bool => in_array($register->shop_id, $this->tenant->accessibleShopIdsFor($user), true))
            ->map(fn (User $user): array => [
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status' => $user->getAttribute('business_status'),
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->sort()->values()->all(),
                // A bcrypt hash: the till checks a PIN offline, and should lock
                // a person out after a few wrong tries.
                'pin_hash' => $pins->get($user->id)?->pin_hash,
                'pin_set_at' => $pins->get($user->id)?->set_at?->toIso8601String(),
                // Locked out on every till until someone unlocks them.
                'pin_locked' => $pins->get($user->id)?->locked_at !== null,
            ])
            ->values()->all();
    }

    /**
     * The business, shop and register settings a till works by.
     *
     * @return array<string, mixed>
     */
    private function settings(Register $register): array
    {
        $business = $this->tenant->business();
        $shop = $register->shop;

        return [
            'business' => [
                'uuid' => $business->uuid,
                'name' => $business->name,
                'currency' => $business->currency,
                'timezone' => $business->timezone,
                'receipt_header' => $business->receipt_header,
                'receipt_footer' => $business->receipt_footer,
            ],
            'shop' => [
                'uuid' => $shop->uuid,
                'name' => $shop->name,
                'code' => $shop->code,
                'phone' => $shop->phone,
                'address' => $shop->address,
                'tax_pin' => $shop->tax_pin,
                'vat_registered' => (bool) $shop->vat_registered,
                'timezone' => $shop->timezone ?? $business->timezone,
            ],
            'register' => [
                'uuid' => $register->uuid,
                'name' => $register->name,
                'code' => $register->code,
                'last_invoice_number' => (int) $register->last_invoice_number,
            ],
            // How the till locks people out after wrong PINs (decided 26 October 2026).
            'till_pin' => [
                'digits' => 4,
                'max_attempts' => (int) config('sync.pin_max_attempts'),
                'lock_minutes' => (int) config('sync.pin_lock_minutes'),
                'lock_doubles' => true,
                'lockouts_before_manager' => (int) config('sync.pin_lockouts_before_manager'),
                'unlock_permission' => (string) config('sync.pin_unlock_permission'),
            ],
        ];
    }

    /**
     * An enum's value, or the value itself.
     */
    private static function plain(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    private static function money(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }
}
