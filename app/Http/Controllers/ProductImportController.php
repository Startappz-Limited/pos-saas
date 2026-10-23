<?php

namespace App\Http\Controllers;

use App\Actions\Product\ImportProductsFromCsv;
use App\Http\Requests\ConfirmProductImportRequest;
use App\Http\Requests\ImportProductsRequest;
use App\Models\Product;
use App\Models\Shop;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Product import from a CSV file (stage 3; fix/product-stock-import).
 *
 * A file that holds products already in the system (matched by SKU) isn't
 * imported straight away: it is kept privately in the business's storage for
 * up to 30 minutes, and a confirmation page shows what would change. The
 * uploader then updates the existing products (stock set to the file's figure
 * in the chosen shop), imports only the new ones, or cancels. The held file is
 * known only to the uploader's session and deleted on any answer.
 */
class ProductImportController extends Controller
{
    private const PENDING = 'product_import.pending';

    private const HOLD_MINUTES = 30;

    public function __construct(private TenantFiles $files) {}

    public function create(): View
    {
        $this->authorize('import', Product::class);
        $this->forgetPending(expiredOnly: true);

        return view('products.import', [
            'shops' => Shop::active()->orderBy('name')->get(),
            'columns' => ImportProductsFromCsv::columns(),
        ]);
    }

    public function store(ImportProductsRequest $request, ImportProductsFromCsv $import): RedirectResponse
    {
        $shop = Shop::query()->findOrFail($request->validated('shop_id'));
        $analysis = $import->analyse($request->file('file')->getRealPath());

        if ($analysis['errors'] !== []) {
            return back()->withInput($request->except('file'))->with('import_errors', $analysis['errors']);
        }

        if ($analysis['existing'] === []) {
            return $this->finish($import->import($analysis, $shop, $request->user()->id), $shop, $analysis['ignored']);
        }

        // Some products exist: hold the file and ask first.
        $this->forgetPending();
        $path = $this->files->storePrivate($request->file('file'), 'imports/pending');
        $request->session()->put(self::PENDING, [
            'path' => $path,
            'shop_id' => $shop->id,
            'name' => $request->file('file')->getClientOriginalName(),
            'expires_at' => now()->addMinutes(self::HOLD_MINUTES)->getTimestamp(),
        ]);

        return redirect()->route('products.import.confirm');
    }

    public function confirm(ImportProductsFromCsv $import): View|RedirectResponse
    {
        $this->authorize('import', Product::class);
        [$pending, $shop] = $this->pending();

        if ($pending === null) {
            return redirect()->route('products.import.create')->with('error', __('That upload has expired. Upload the file again.'));
        }

        $analysis = $import->analyse(Storage::disk(TenantFiles::PRIVATE_DISK)->path($pending['path']));
        $existingRows = collect($analysis['rows'])->filter(fn (array $row): bool => $row['sku'] !== null && isset($analysis['existing'][Str::lower($row['sku'])]))
            ->map(fn (array $row): array => ['row' => $row, 'product' => $product = $analysis['existing'][Str::lower($row['sku'])], 'preview' => $import->preview($product, $row, $shop)])
            ->values();

        return view('products.import-confirm', [
            'shop' => $shop,
            'fileName' => $pending['name'],
            'existing' => $existingRows,
            'newCount' => count($analysis['rows']) - $existingRows->count(),
            'ignored' => $analysis['ignored'],
            'expiresAt' => now()->setTimestamp($pending['expires_at']),
        ]);
    }

    public function decide(ConfirmProductImportRequest $request, ImportProductsFromCsv $import): RedirectResponse
    {
        [$pending, $shop] = $this->pending();
        $decision = $request->validated('decision');

        if ($pending === null) {
            return redirect()->route('products.import.create')->with('error', __('That upload has expired. Upload the file again.'));
        }

        if ($decision === ConfirmProductImportRequest::CANCEL) {
            $this->forgetPending();

            return redirect()->route('products.import.create')->with('info', __('Import cancelled. Nothing was changed.'));
        }

        $analysis = $import->analyse(Storage::disk(TenantFiles::PRIVATE_DISK)->path($pending['path']));
        $this->forgetPending();

        if ($analysis['errors'] !== []) {
            return redirect()->route('products.import.create')->with('import_errors', $analysis['errors']);
        }

        $mode = $decision === ConfirmProductImportRequest::UPDATE ? ImportProductsFromCsv::UPDATE_EXISTING : ImportProductsFromCsv::SKIP_EXISTING;

        return $this->finish($import->import($analysis, $shop, $request->user()->id, $mode), $shop, $analysis['ignored']);
    }

    public function template(): StreamedResponse
    {
        $this->authorize('import', Product::class);

        return response()->streamDownload(fn () => print ImportProductsFromCsv::template(), 'products-template.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * The products list with what was done, and the ignored columns as a warning.
     *
     * @param  array{created: int, updated: int, unchanged: int, skipped: int, opening_stock: int, errors: list<string>}  $result
     * @param  list<string>  $ignored
     */
    private function finish(array $result, Shop $shop, array $ignored): RedirectResponse
    {
        $parts = [trans_choice('{0} No new products|{1} 1 new product imported|[2,*] :count new products imported', $result['created'], ['count' => $result['created']])
            .' '.__('into :shop', ['shop' => $shop->name])
            .($result['created'] > 0 ? ', '.trans_choice('{0} with no opening stock|{1} with 1 unit of opening stock|[2,*] with :count units of opening stock', $result['opening_stock'], ['count' => $result['opening_stock']]) : '')];

        if ($result['updated'] > 0) {
            $parts[] = trans_choice('{1} 1 existing product updated (stock set to the file\'s figure)|[2,*] :count existing products updated (stock set to the file\'s figures)', $result['updated'], ['count' => $result['updated']]);
        }
        if ($result['unchanged'] > 0) {
            $parts[] = trans_choice('{1} 1 existing product already matched the file|[2,*] :count existing products already matched the file', $result['unchanged'], ['count' => $result['unchanged']]);
        }
        if ($result['skipped'] > 0) {
            $parts[] = trans_choice('{1} 1 existing product left as it was|[2,*] :count existing products left as they were', $result['skipped'], ['count' => $result['skipped']]);
        }

        $redirect = redirect()->route('products.index')->with('success', implode('. ', $parts).'.');

        return $ignored === [] ? $redirect
            : $redirect->with('warning', __('Ignored columns: :columns. Their values were not imported.', ['columns' => implode(', ', $ignored)]));
    }

    /**
     * The held upload and its shop, or nulls when there is none or it expired.
     *
     * @return array{0: ?array{path: string, shop_id: int, name: string, expires_at: int}, 1: ?Shop}
     */
    private function pending(): array
    {
        $pending = session(self::PENDING);

        if ($pending === null || $pending['expires_at'] < now()->getTimestamp() || ! $this->files->owns($pending['path'])) {
            $this->forgetPending();

            return [null, null];
        }

        $shop = Shop::query()->find($pending['shop_id']);

        return $shop === null ? [null, null] : [$pending, $shop];
    }

    /**
     * Deletes the held upload and forgets it (only once expired, when asked).
     */
    private function forgetPending(bool $expiredOnly = false): void
    {
        $pending = session(self::PENDING);

        if ($pending === null || ($expiredOnly && $pending['expires_at'] >= now()->getTimestamp())) {
            return;
        }

        if ($this->files->owns($pending['path'])) {
            Storage::disk(TenantFiles::PRIVATE_DISK)->delete($pending['path']);
        }

        session()->forget(self::PENDING);
    }
}
