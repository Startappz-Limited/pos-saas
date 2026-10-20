<?php

namespace App\Http\Controllers;

use App\Actions\Business\CloseBusiness;
use App\Actions\Business\HandOverBusiness;
use App\Actions\Business\SendOwnerConfirmationCode;
use App\Http\Middleware\ResolveTenant;
use App\Jobs\BuildBusinessExport;
use App\Models\BusinessClosure;
use App\Models\BusinessExport;
use App\Models\OwnerConfirmation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The business's data: exports, and closing the business (stage 3). Owners
 * only.
 */
class BusinessDataController extends Controller
{
    public function __construct(
        private TenantContext $tenant,
        private SendOwnerConfirmationCode $codes,
        private CloseBusiness $closer,
        private HandOverBusiness $handover,
    ) {}

    public function index(Request $request): View
    {
        $this->ownersOnly($request);

        return view('business.data', [
            'exports' => BusinessExport::query()->with('requester:id,name')->latest()->limit(10)->get(),
            'pendingCode' => $this->codes->pending($request->user(), OwnerConfirmation::PURPOSE_CLOSE),
            'graceDays' => CloseBusiness::graceDays(),
            'isPrimaryOwner' => $this->mayHandOver($request->user()),
            'coOwners' => $this->handover->candidates($request->user()),
            'pendingHandover' => $this->codes->pending($request->user(), OwnerConfirmation::PURPOSE_HANDOVER),
        ]);
    }

    /**
     * Step 1 of closing: a one-time code emailed to the owner asking.
     */
    public function closureCode(Request $request): RedirectResponse
    {
        $this->ownersOnly($request);

        $this->codes->execute($request->user(), OwnerConfirmation::PURPOSE_CLOSE);

        return redirect()->route('business.data')
            ->with('success', __('We emailed you a confirmation code. Enter it below to close the business.'));
    }

    /**
     * Step 2: the code confirms, and the closure starts.
     */
    public function close(Request $request): RedirectResponse
    {
        $this->ownersOnly($request);

        $validated = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $confirmation = $this->codes->pending($request->user(), OwnerConfirmation::PURPOSE_CLOSE);

        if ($confirmation === null || ! $confirmation->attempt($validated['code'])) {
            return back()->withErrors(['code' => __('That code is wrong or has expired. Ask for a new one if needed.')]);
        }

        $business = $this->tenant->business();
        $closure = $this->closer->start($business, $request->user());
        $request->session()->forget(ResolveTenant::SESSION_KEY);

        return redirect()->route('businesses.index')->with('success', __(
            ':business is closing. Its final export is on its way to the owners; its data is erased on :date unless an owner cancels before then.',
            ['business' => $business->name, 'date' => $closure->delete_after->toFormattedDayDateString()],
        ));
    }

    /**
     * An owner reopens a closing business, during its grace period.
     */
    public function cancelClosure(Request $request, BusinessClosure $closure): RedirectResponse
    {
        abort_if($closure->cancelled_at !== null || $closure->erased_at !== null, 404);
        abort_unless($this->tenant->run($closure->business_id, fn (): bool => $this->isOwner($request->user())), 404);

        $this->closer->cancel($closure, $request->user());
        $request->session()->put(ResolveTenant::SESSION_KEY, $closure->business_id);

        return redirect()->route('dashboard')->with('success', __(':business is open again.', ['business' => $closure->business_name]));
    }

    /**
     * Step 1 of a handover: a code emailed to the owner, naming the co-owner.
     */
    public function handoverCode(Request $request): RedirectResponse
    {
        abort_unless($this->mayHandOver($request->user()), 403);

        $validated = $request->validate(['owner' => ['required', 'string']]);
        $target = $this->handover->candidates($request->user())->firstWhere('uuid', $validated['owner']);

        if ($target === null) {
            return back()->withErrors(['owner' => __('Ownership can only pass to an existing co-owner of this business.')]);
        }

        $this->codes->execute($request->user(), OwnerConfirmation::PURPOSE_HANDOVER, ['owner_id' => $target->id]);

        return redirect()->route('business.data')
            ->with('success', __('We emailed you a confirmation code. Enter it below to hand the business over to :name.', ['name' => $target->name]));
    }

    /**
     * Step 2: the code confirms, and ownership passes.
     */
    public function handover(Request $request): RedirectResponse
    {
        abort_unless($this->mayHandOver($request->user()), 403);

        $validated = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $confirmation = $this->codes->pending($request->user(), OwnerConfirmation::PURPOSE_HANDOVER);

        if ($confirmation === null || ! $confirmation->attempt($validated['code'])) {
            return back()->withErrors(['code' => __('That code is wrong or has expired. Ask for a new one if needed.')]);
        }

        // Checked again: the co-owner may have been removed since the code was sent.
        $target = $this->handover->candidates($request->user())->firstWhere('id', $confirmation->payload['owner_id'] ?? 0);

        if ($target === null) {
            return back()->withErrors(['code' => __('That person is no longer an owner of this business.')]);
        }

        $this->handover->execute($request->user(), $target);

        return redirect()->route('business.data')->with('success', __(':name is now the owner of :business. You remain a co-owner.', [
            'name' => $target->name,
            'business' => $this->tenant->business()->name,
        ]));
    }

    public function export(Request $request): RedirectResponse
    {
        $this->ownersOnly($request);

        $export = BusinessExport::create(['requested_by' => $request->user()->id]);
        BuildBusinessExport::dispatch($export->id);

        return redirect()->route('business.data')
            ->with('success', __('The export is being prepared. We will email the owners when it is ready.'));
    }

    /**
     * From the emailed (signed) link, or the business data page. Works while
     * the business is closing, when it can't be opened, so it finds the export
     * in whichever business it belongs to, then requires an owner of it.
     */
    public function download(Request $request, string $export): StreamedResponse
    {
        $record = BusinessExport::findAcrossBusinesses($export);

        abort_if($record === null || ! $record->isReady(), 404);
        abort_unless($this->tenant->run($record->business_id, fn (): bool => $this->isOwner($request->user())), 404);

        return Storage::disk(TenantFiles::PRIVATE_DISK)->download($record->path, "export-{$record->uuid}.zip");
    }

    private function ownersOnly(Request $request): void
    {
        abort_unless($this->isOwner($request->user()), 403);
    }

    /**
     * Only the founding owner hands the business over (any owner, if the
     * business has none recorded).
     */
    private function mayHandOver(?User $user): bool
    {
        if (! $this->isOwner($user)) {
            return false;
        }

        $primary = $this->tenant->business()->primary_owner_id;

        return $primary === null || $primary === $user->id;
    }

    private function isOwner(?User $user): bool
    {
        return $user !== null && $user->fresh()->isBusinessOwner();
    }
}
