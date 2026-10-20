<?php

namespace App\Support\Onboarding;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;

/**
 * What a new business still has to do before it is trading (stage 3): its
 * details, a first shop, a first cash register session, products and a first
 * sale. Each step is judged from the business's own data, so the list ticks
 * itself off; counts go through the tenant-scoped models.
 */
class OnboardingChecklist
{
    /**
     * @return list<array{key: string, label: string, hint: string, done: bool, url: string}>
     */
    public function steps(): array
    {
        $business = current_business();

        return [
            [
                'key' => 'business',
                'label' => __('Set up your business'),
                'hint' => __('Name, logo, currency and receipt text.'),
                'done' => $business !== null && ($business->logo_path !== null || $business->receipt_header !== null || $business->invoice_prefix !== null),
                'url' => route('business.settings.edit'),
            ],
            [
                'key' => 'shop',
                'label' => __('Add your first shop'),
                'hint' => __('Where you sell from.'),
                'done' => Shop::query()->exists(),
                'url' => route('shops.create'),
            ],
            [
                'key' => 'register',
                'label' => __('Open a cash register'),
                'hint' => __('Start a till session to take sales.'),
                'done' => CashRegister::query()->exists(),
                'url' => route('cash-registers.open'),
            ],
            [
                'key' => 'products',
                'label' => __('Add your products'),
                'hint' => __('One by one, or import a CSV file.'),
                'done' => Product::query()->exists(),
                'url' => route('products.import.create'),
            ],
            [
                'key' => 'sale',
                'label' => __('Make your first sale'),
                'hint' => __('Ring up a sale at the till.'),
                'done' => Sale::query()->exists(),
                'url' => route('sales.create'),
            ],
        ];
    }

    public function complete(): bool
    {
        return collect($this->steps())->every(fn (array $step): bool => $step['done']);
    }
}
