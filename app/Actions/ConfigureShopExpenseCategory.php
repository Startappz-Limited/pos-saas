<?php

namespace App\Actions;

use App\Models\ExpenseCategory;
use App\Models\ShopExpenseCategorySetting;

class ConfigureShopExpenseCategory
{
    /**
     * Configure a shop-specific expense category setting.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(ExpenseCategory $category, int $shopId, array $data): ShopExpenseCategorySetting
    {
        $setting = ShopExpenseCategorySetting::updateOrCreate(
            [
                'shop_id' => $shopId,
                'expense_category_id' => $category->id,
            ],
            [
                'is_enabled' => $data['is_enabled'] ?? true,
                'monthly_budget' => $data['monthly_budget'] ?? null,
                'yearly_budget' => $data['yearly_budget'] ?? null,
                'requires_approval' => $data['requires_approval'] ?? null,
                'approval_threshold' => $data['approval_threshold'] ?? null,
                'updated_by' => auth()->id(),
            ]
        );

        // Set created_by only on creation
        if ($setting->wasRecentlyCreated) {
            $setting->update(['created_by' => auth()->id()]);
        }

        return $setting;
    }
}
