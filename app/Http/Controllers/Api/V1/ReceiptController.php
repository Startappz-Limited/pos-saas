<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiV1;
use App\Models\Sale;
use App\Support\Receipts\SaleReceipt;
use Illuminate\Http\JsonResponse;

/**
 * A sale's receipt for an ESC/POS printer (Phase 7): a till reprints one, or
 * prints one for a sale made online. Only someone who may see the sale gets
 * it; another shop's or business's sale is a 404.
 */
class ReceiptController extends Controller
{
    public function show(Sale $sale, SaleReceipt $receipt): JsonResponse
    {
        $this->authorize('view', $sale);

        return ApiV1::ok($receipt->build($sale));
    }
}
