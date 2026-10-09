<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashRegisterResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'shop_id' => $this->shop_id,
            'user_id' => $this->user_id,
            'register_number' => $this->register_number,
            'register_date' => $this->register_date?->toDateString(),
            'opening_balance' => (float) $this->opening_balance,
            'expense_opening_balance' => (float) $this->expense_opening_balance,
            'expense_balance_used' => (float) $this->expense_balance_used,
            'remaining_expense_balance' => (float) $this->getRemainingExpenseBalance(),
            'closing_balance' => $this->closing_balance !== null ? (float) $this->closing_balance : null,
            'expected_balance' => $this->expected_balance !== null ? (float) $this->expected_balance : null,
            'variance' => $this->variance !== null ? (float) $this->variance : null,
            'total_sales' => (float) $this->total_sales,
            'total_cash_sales' => (float) $this->total_cash_sales,
            'total_card_sales' => (float) $this->total_card_sales,
            'total_credit_sales' => (float) $this->total_credit_sales,
            'total_mobile_money_sales' => (float) $this->total_mobile_money_sales,
            'total_bank_transfer_sales' => (float) $this->total_bank_transfer_sales,
            'total_cheque_sales' => (float) $this->total_cheque_sales,
            'transaction_count' => (int) $this->transaction_count,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toISOString(),
            'closed_at' => $this->closed_at?->toISOString(),
            'opening_notes' => $this->opening_notes,
            'closing_notes' => $this->closing_notes,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'closed_by' => $this->whenLoaded('closedBy', fn () => $this->closedBy ? [
                'id' => $this->closedBy->id,
                'name' => $this->closedBy->name,
            ] : null),
            'expenses' => $this->whenLoaded('expenses', fn () => $this->expenses->map(fn ($expense) => [
                'id' => $expense->id,
                'title' => $expense->title,
                'amount' => (float) $expense->amount,
                'payment_method' => $expense->payment_method,
                'expense_date' => $expense->expense_date?->toDateString(),
                'category' => $expense->category?->name,
                'settled_from_register' => (bool) $expense->settled_from_register,
            ])),
            'expenses_total' => (float) $this->whenLoaded('expenses', fn () => $this->expenses->sum('amount'), 0),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
