<?php

namespace App\Jobs;

use App\Enums\AlertSeverity;
use App\Models\AbandonedCart;
use App\Services\Integration\AbandonedCartWriteBack;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Pushes a POS decision about a cart (sold / customer opted out) back to the
 * website. Runs after the POS action has committed and never undoes it: the
 * outcome, good or bad, lands on the cart's timeline for staff to see.
 */
class AbandonedCartWriteBackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public AbandonedCart $cart,
        public string $status,
        public ?string $reference = null,
        public ?string $note = null,
    ) {
        $this->onQueue('ecommerce');
    }

    public function handle(AbandonedCartWriteBack $writeBack): void
    {
        $result = $writeBack->setStatus($this->cart, $this->status, $this->reference, $this->note);

        if ($result['success']) {
            // The website does not echo a POS recovery back as a webhook, so the
            // POS records what the website now says itself.
            $this->recordWebsiteState($result['data'] ?? []);

            $this->cart->logActivity(
                'Website updated: cart marked '.$this->status,
                $this->reference ? "Reference {$this->reference}" : '',
                ['event' => 'write_back', 'status' => $this->status, 'reference' => $this->reference],
            );

            return;
        }

        if ($result['retryable'] && $this->attempts() < $this->tries) {
            // Try again later. Released rather than thrown, so a website outage
            // can never surface as an exception in whatever dispatched this.
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);

            return;
        }

        $this->recordFailure($result['message'] ?? 'Website update failed.');
    }

    /**
     * @param  array<string, mixed>  $data  {id, status, unsubscribed}
     */
    private function recordWebsiteState(array $data): void
    {
        $updates = ['last_activity' => 'website_'.$this->status];

        if (is_string($data['status'] ?? null) && $data['status'] !== '') {
            $updates['platform_status'] = $data['status'];
        }

        if ($this->status === AbandonedCartWriteBack::STATUS_RECOVERED) {
            $updates['recovered_at'] = $this->cart->recovered_at ?? now();
        }

        $this->cart->forceFill($updates)->save();
    }

    public function failed(?\Throwable $exception): void
    {
        $this->recordFailure($exception?->getMessage() ?: 'Website update failed.');
    }

    private function recordFailure(string $message): void
    {
        Log::warning('Abandoned-cart write-back gave up', [
            'cart_id' => $this->cart->id,
            'status' => $this->status,
            'message' => $message,
        ]);

        try {
            $this->cart->logActivity(
                "Website NOT updated (wanted: {$this->status})",
                $message.' The POS change still stands; update the cart on the website by hand if reminders must stop.',
                ['event' => 'write_back_failed', 'status' => $this->status, 'reference' => $this->reference],
                severity: AlertSeverity::MEDIUM,
            );
        } catch (\Throwable $e) {
            Log::error('Could not record write-back failure on cart timeline', ['cart_id' => $this->cart->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Queue a write-back without ever letting a queue problem break the caller.
     */
    public static function dispatchSafely(AbandonedCart $cart, string $status, ?string $reference = null, ?string $note = null): void
    {
        try {
            static::dispatch($cart, $status, $reference, $note)->afterCommit();
        } catch (\Throwable $e) {
            Log::error('Could not queue abandoned-cart write-back', [
                'cart_id' => $cart->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
