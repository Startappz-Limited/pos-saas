<?php

namespace App\Actions\Business;

use App\Models\Business;
use Illuminate\Support\Facades\DB;

class CancelBusinessClosureAction
{
    /**
     * Reopens a closing business. Nothing was deleted while it was closing,
     * so staff can sign in again straight away. The final export goes (it
     * holds personal data and no longer has a purpose).
     */
    public function execute(Business $business): Business
    {
        DB::transaction(function () use ($business): void {
            $business->update([
                'closing_requested_at' => null,
                'purge_after' => null,
                'closing_requested_by' => null,
            ]);

            $business->exports()->where('is_final', true)->get()->each->delete();
        });

        return $business;
    }
}
