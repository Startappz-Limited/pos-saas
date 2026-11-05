<?php

namespace App\Support\Sales;

use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

/**
 * What the web sale page shows about voiding (stage 10, step 5a), for one
 * person: the Void button when SalePolicy::void allows it, with a muted line
 * saying until when; otherwise the reason in one line (past the window, or a
 * return), or nothing for someone without the permission. Also what the
 * confirmation window says will happen to the stock and the money.
 */
final class VoidButton
{
    public function __construct(private VoidWindow $window, private VoidRefunds $refunds) {}

    /**
     * @return array{show: bool, until: ?string, reason: ?string, items: int, shop: string, invoice: string, money: list<string>}
     */
    public function for(Sale $sale, User $user): array
    {
        $decision = Gate::forUser($user)->inspect('void', $sale);
        $show = $decision->allowed();

        return [
            'show' => $show,
            'until' => $show ? $this->untilLine($sale) : null,
            'reason' => ! $show && $decision->status() === 422 ? $this->reasonLine($sale, (string) $decision->code(), (string) $decision->message()) : null,
            'items' => (int) $sale->items->sum('quantity'),
            'shop' => (string) $sale->shop?->name,
            'invoice' => (string) $sale->invoice_number,
            'money' => $show ? $this->moneyLines($sale, $user) : [],
        ];
    }

    private function untilLine(Sale $sale): ?string
    {
        $until = $this->window->until($sale);
        if ($until === null) {
            return null; // no limit, or a sale that isn't completed yet
        }

        $local = $until->setTimezone($this->window->timezone($sale));
        $mode = $this->window->mode($sale);

        return match (true) {
            is_int($mode) => __('Can be voided for another :minutes minutes.', ['minutes' => max(1, (int) ceil(now()->diffInSeconds($until) / 60))]),
            $mode === VoidWindow::SESSION => __('Can be voided until the register session closes (:time today at the latest).', ['time' => $local->format('g:i a')]),
            default => __('Can be voided until :time today.', ['time' => $local->format('g:i a')]),
        };
    }

    private function reasonLine(Sale $sale, string $code, string $message): string
    {
        if ($code === 'sale_has_returns') {
            return __('This sale has a return, so it can\'t be voided.');
        }

        $mode = $this->window->mode($sale);
        if (is_int($mode) && ($until = $this->window->until($sale)) !== null) {
            return __('Voiding closed :time ago (limit :minutes minutes). Use a return instead.', [
                'time' => $until->diffForHumans(now(), ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 1]),
                'minutes' => $mode,
            ]);
        }

        return $message;
    }

    /**
     * What becomes of the money, per the decisions of 4 November 2026.
     *
     * @return list<string>
     */
    private function moneyLines(Sale $sale, User $user): array
    {
        $own = $this->refunds->ownSession($user, (int) $sale->shop_id) !== null;
        $sessionOpen = $sale->register?->status === 'open';
        $lines = [];

        foreach ($this->refunds->received($sale) as $part) {
            $amount = number_format($part['amount'], 2);
            $lines[] = match (true) {
                $part['method'] === 'cash' && $part['in_session_totals'] && $sessionOpen => __('Cash :amount comes back out of its register session, which no longer counts the sale. Hand it back to the customer.', ['amount' => $amount]),
                $part['method'] === 'cash' && $own => __('Cash :amount is paid back from your open register.', ['amount' => $amount]),
                $part['method'] === 'cash' => __('Cash :amount is listed as a refund owed: you have no register open in this shop.', ['amount' => $amount]),
                default => __(':method :amount is listed as a refund owed, to refund outside the system and record.', ['method' => self::methodLabel($part['method']), 'amount' => $amount]),
            };
        }

        return $lines;
    }

    private static function methodLabel(string $method): string
    {
        return match ($method) {
            'card' => __('Card'),
            'mobile_money' => __('M-Pesa'),
            'bank_transfer' => __('Bank transfer'),
            'cheque' => __('Cheque'),
            default => ucfirst($method),
        };
    }
}
