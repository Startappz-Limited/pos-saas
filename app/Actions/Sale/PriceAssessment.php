<?php

namespace App\Actions\Sale;

use App\Models\Product;

/**
 * What CheckSalePrices found for a sale (stage 11b): each line's expected and charged prices and whether it is short
 * and approved, the discount against the seller's limit, and the refusals an online sale must not go through with.
 */
final class PriceAssessment
{
    /**
     * @param  list<array<string, mixed>>  $lines  one per sale line, in the order given
     * @param  array<string, mixed>  $discount  amount, percent, limit, within, approver, approved, approved_at, refused
     * @param  list<array<string, mixed>>  $refusals  a line (`index`) or the discount (`index` null) with its `code`
     */
    public function __construct(
        public readonly array $lines,
        public readonly array $discount,
        public readonly array $refusals,
        public readonly bool $online,
    ) {}

    public function refused(): bool
    {
        return $this->refusals !== [];
    }

    /**
     * The lines charged below their expected price (whether or not approved).
     *
     * @return list<array<string, mixed>>
     */
    public function shortLines(): array
    {
        return array_values(array_filter($this->lines, fn (array $line): bool => $line['short']));
    }

    /**
     * Whether the discount is a difference the sale may keep: none, within the limit, or approved.
     */
    public function discountAllowed(): bool
    {
        return (bccomp($this->discount['amount'], '0', 2) <= 0) || $this->discount['within'] || $this->discount['approved'];
    }

    /**
     * Who approved the discount, when it was above the seller's limit and approved; NULL otherwise.
     */
    public function discountApproverId(): ?int
    {
        return ($this->discount['approved'] ?? false) && ! ($this->discount['within'] ?? true) ? $this->discount['approver']?->id : null;
    }

    /**
     * When the discount was approved (only meaningful where discountApproverId() is set).
     */
    public function discountApprovedAt(): mixed
    {
        return $this->discount['approved_at'] ?? null;
    }

    /**
     * The approval a line carries: the approver and when, when the lower price was approved; empty otherwise.
     *
     * @return array{expected_unit_price: ?string, price_overridden_by: ?int, override_approved_at: mixed}
     */
    public function lineFields(int $index): array
    {
        $line = $this->lines[$index] ?? [];
        $approved = (bool) ($line['approved'] ?? false);

        return [
            'expected_unit_price' => $line['expected_unit_price'] ?? null,
            'price_overridden_by' => $approved ? ($line['approver']?->id) : null,
            'override_approved_at' => $approved ? ($line['approved_at'] ?? null) : null,
        ];
    }

    /**
     * The words a refused sale gives, by the field they belong to: `items.N.price` for a line, `discount_amount` for the
     * discount. Each names the expected price or the limit, so the person knows what to ask an approver to allow.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, string>
     */
    public function messages(array $items): array
    {
        $messages = [];

        foreach ($this->refusals as $refusal) {
            if ($refusal['code'] === 'price_below_expected') {
                $name = Product::query()->whereKey($items[$refusal['index']]['product_id'])->value('name');
                $messages["items.{$refusal['index']}.price"] = __('The price for :item is below :expected. Ask someone who can override prices.', [
                    'item' => $name ?? __('item :n', ['n' => $refusal['index'] + 1]),
                    'expected' => 'KES '.number_format((float) $refusal['expected_unit_price'], 2),
                ]);

                continue;
            }

            $messages['discount_amount'] = __('This discount is above your limit of :limit%. Ask someone who can override prices.', [
                'limit' => rtrim(rtrim((string) $refusal['limit'], '0'), '.'),
            ]);
        }

        return $messages;
    }
}
