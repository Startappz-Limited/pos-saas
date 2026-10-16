<?php

namespace Tests\Support;

use App\Models\AbandonedCart;
use App\Models\Attribute;
use App\Models\BaileysSession;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\DeliveryCompany;
use App\Models\EcommerceOrder;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PricingRule;
use App\Models\PurchaseOrder;
use App\Models\SaleSource;
use App\Models\SocialAccount;
use App\Support\Tenancy\TenantContext;

/**
 * Marked records for the tenant isolation suite (Phase 2): one of each main
 * kind in a business, every label carrying the business's marker, so a page
 * or response that shows another business's data is caught by searching for
 * the marker. The fixture's own records (shops, people, customer, product,
 * supplier) already carry it.
 */
final class Canaries
{
    /**
     * Plants the records in $fixture's business and returns the strings no
     * other business may see: the marker and the uuids of the business's
     * records.
     *
     * @return list<string>
     */
    public static function plant(BusinessFixture $fixture, string $marker): array
    {
        return app(TenantContext::class)->run($fixture->business, function () use ($fixture, $marker): array {
            $shop = $fixture->shops[0];

            Category::factory()->create(['name' => "{$marker} Category"]);
            Attribute::factory()->create(['name' => "{$marker} Attribute"]);
            DeliveryCompany::factory()->create(['name' => "{$marker} Couriers"]);
            SaleSource::factory()->create(['name' => "{$marker} Source"]);
            ExpenseCategory::factory()->create(['name' => "{$marker} Expense Category"]);
            PricingRule::factory()->create(['name' => "{$marker} Pricing Rule"]);
            Campaign::factory()->create(['name' => "{$marker} Campaign", 'shop_id' => $shop->id]);
            Expense::factory()->create(['title' => "{$marker} Expense", 'shop_id' => $shop->id]);
            EcommerceOrder::factory()->create(['customer_name' => "{$marker} Online Buyer", 'shop_id' => $shop->id]);
            AbandonedCart::factory()->create(['customer_name' => "{$marker} Cart Shopper", 'shop_id' => $shop->id]);
            PurchaseOrder::factory()->create(['notes' => "{$marker} Purchase Order", 'shop_id' => $shop->id, 'supplier_id' => $fixture->supplier->id]);
            BaileysSession::factory()->create(['name' => "{$marker} WhatsApp", 'shop_id' => $shop->id]);
            SocialAccount::factory()->create(['account_name' => "{$marker} Page", 'shop_id' => $shop->id]);

            return [
                $marker,
                ...collect($fixture->shops)->pluck('uuid')->all(),
                $fixture->customer->uuid,
                $fixture->product->uuid,
                $fixture->sale->uuid,
            ];
        });
    }
}
