<?php

namespace App\Http\Controllers;

use App\Actions\Shop\SaveShopMpesaAccount;
use App\Http\Requests\SaveShopMpesaRequest;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * A shop's own M-Pesa Till or Paybill and account details (stage 3). Saved
 * only after a test query to the M-Pesa API succeeds.
 */
class ShopMpesaController extends Controller
{
    public function edit(Shop $shop): View
    {
        $this->authorize('update', $shop);

        return view('shops.mpesa', ['shop' => $shop]);
    }

    public function update(SaveShopMpesaRequest $request, Shop $shop, SaveShopMpesaAccount $save): RedirectResponse
    {
        $this->authorize('update', $shop);

        $save->execute($shop, $request->validated(), $request->user()->id);

        return redirect()->route('shops.mpesa.edit', $shop)
            ->with('success', __('M-Pesa details checked and saved.'));
    }
}
