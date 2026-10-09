<?php

namespace App\Http\Controllers;

use App\Actions\Business\CancelBusinessClosureAction;
use App\Actions\Business\CloseBusinessAction;
use App\Actions\Business\RequestBusinessExportAction;
use App\Models\Business;
use App\Models\BusinessExport;
use App\Models\User;
use App\Notifications\BusinessClosureCodeNotification;
use App\Support\BusinessClosureCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Closing a business, owner only: download an export of the data first, then
 * ask to close. The business is frozen for Business::GRACE_DAYS (only the
 * owner can sign in, to this controller's pages) and its data deleted after.
 */
class BusinessClosureController extends Controller
{
    public function requestExport(Request $request, RequestBusinessExportAction $requestExport): RedirectResponse
    {
        $business = $this->ownBusiness($request);
        Gate::authorize('export', $business);

        $requestExport->execute($business, $request->user());

        return redirect()->route('profile.edit')
            ->with('success', __('Your export is being prepared. We will email a link to :email when it is ready.', ['email' => $request->user()->email]));
    }

    public function download(BusinessExport $export): StreamedResponse
    {
        Gate::authorize('download', $export);

        abort_unless($export->isReady() && Storage::disk(BusinessExport::DISK)->exists($export->path), 404);

        if (! $export->wasDownloaded()) {
            $export->update(['downloaded_at' => now()]);
        }

        return Storage::disk(BusinessExport::DISK)->download($export->path, $export->downloadName());
    }

    /**
     * Step 1 of confirming: the owner's email and password (and that they kept
     * their export). Emails a one-time code to finish with.
     */
    public function sendClosureCode(Request $request): RedirectResponse
    {
        $business = $this->ownBusiness($request);
        Gate::authorize('close', $business);
        $owner = $request->user();

        $request->validateWithBag('businessClosure', [
            'email' => ['required', 'string', 'email', function (string $attribute, mixed $value, \Closure $fail) use ($owner): void {
                if (Str::lower(trim((string) $value)) !== Str::lower($owner->email)) {
                    $fail(__('Enter the email address of your account.'));
                }
            }],
            'password' => ['required', 'current_password'],
            'export_kept' => ['accepted'],
        ], [
            'export_kept.accepted' => __('Confirm that you have downloaded and kept your export.'),
        ]);

        if ($error = $this->closingBlockedReason($business)) {
            return $error;
        }

        return $this->emailClosureCode($business, $owner);
    }

    /**
     * Sends a fresh code while one is still pending (the owner already gave
     * their email and password for it).
     */
    public function resendClosureCode(Request $request): RedirectResponse
    {
        $business = $this->ownBusiness($request);
        Gate::authorize('close', $business);

        if (! BusinessClosureCode::pending($business)) {
            return $this->restartClosure(__('Your code has expired. Please confirm your email and password again.'));
        }

        return $this->emailClosureCode($business, $request->user());
    }

    /**
     * Discards a pending code, back to step 1.
     */
    public function restartClosureCode(Request $request): RedirectResponse
    {
        $business = $this->ownBusiness($request);
        Gate::authorize('close', $business);

        BusinessClosureCode::clear($business);

        return redirect()->route('profile.edit');
    }

    /**
     * Step 2: the emailed code. Closes the business.
     */
    public function close(Request $request, CloseBusinessAction $closeBusiness): RedirectResponse
    {
        $business = $this->ownBusiness($request);
        Gate::authorize('close', $business);

        $request->validateWithBag('businessClosure', [
            'code' => ['required', 'string'],
        ]);

        if (! BusinessClosureCode::pending($business)) {
            return $this->restartClosure(__('Your code has expired or was entered wrongly too many times. Please start again.'));
        }

        if (! BusinessClosureCode::verify($business, (string) $request->input('code'))) {
            if (! BusinessClosureCode::pending($business)) {
                return $this->restartClosure(__('That code was entered wrongly too many times. Please start again.'));
            }

            return redirect()->route('profile.edit')
                ->withErrors(['code' => __('That code is not right. Check the latest email we sent you.')], 'businessClosure');
        }

        if ($error = $this->closingBlockedReason($business)) {
            return $error;
        }

        $closeBusiness->execute($business, $request->user());

        return redirect()->route('business.closing')
            ->with('success', __(':business will be closed on :date. We have emailed you the details.', [
                'business' => $business->name,
                'date' => $business->purge_after->toFormattedDayDateString(),
            ]));
    }

    public function closing(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $business = $user->business;

        if (! $user->isBusinessOwner() || ! $business?->isClosing()) {
            return redirect()->route('dashboard');
        }

        return view('business.closing', [
            'business' => $business,
            'finalExport' => $business->finalExport,
        ]);
    }

    public function cancel(Request $request, CancelBusinessClosureAction $cancelClosure): RedirectResponse
    {
        $business = $this->ownBusiness($request);
        Gate::authorize('cancelClosure', $business);

        $cancelClosure->execute($business);

        return redirect()->route('dashboard')
            ->with('success', __(':business is open again. Your staff can sign in as before.', ['business' => $business->name]));
    }

    private function ownBusiness(Request $request): Business
    {
        $business = $request->user()->business;

        abort_if($business === null, 403);

        return $business;
    }

    /**
     * Why the business cannot be closed yet, as a redirect, or null.
     */
    private function closingBlockedReason(Business $business): ?RedirectResponse
    {
        if ($business->canBeClosed()) {
            return null;
        }

        BusinessClosureCode::clear($business);

        return redirect()->route('profile.edit')->withErrors([
            'export_kept' => __('Download an export of your data first. It must be less than :days days old.', ['days' => Business::EXPORT_VALID_DAYS]),
        ], 'businessClosure');
    }

    private function emailClosureCode(Business $business, User $owner): RedirectResponse
    {
        if ($wait = BusinessClosureCode::resendAvailableIn($business)) {
            return redirect()->route('profile.edit')
                ->withErrors(['code' => __('Please wait :seconds seconds before asking for another code.', ['seconds' => $wait])], 'businessClosure');
        }

        $owner->notify(new BusinessClosureCodeNotification($business, BusinessClosureCode::issue($business)));

        return redirect()->route('profile.edit')->with('closure-code-sent', true);
    }

    private function restartClosure(string $message): RedirectResponse
    {
        return redirect()->route('profile.edit')->withErrors(['email' => $message], 'businessClosure');
    }
}
