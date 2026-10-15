<?php

use App\Jobs\PublishCampaignPostJob;
use App\Models\CampaignPost;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The sweeps run once per business that takes background work (stage 1c):
 * suspended businesses are skipped, and each run sees only its business's
 * data. The jobs they dispatch carry that business.
 */

/**
 * Sweep scheduled campaign posts whose time has come and dispatch a publish job
 * for each one. Idempotent: the job re-checks state before posting.
 */
Schedule::call(function (): void {
    app(TenantContext::class)->eachBusiness(fn () => CampaignPost::duePublishing()
        ->limit(50)
        ->pluck('id')
        ->each(fn (int $id) => PublishCampaignPostJob::dispatch($id)));
})->everyFiveMinutes()->name('publish-due-campaign-posts')->withoutOverlapping();

/**
 * Expire stored WhatsApp media. Without this, inbound media from every
 * connected client phone accumulates in storage/app/public/baileys/ forever.
 * Retention window: config('baileys.media.retention_days').
 */
Schedule::call(function (): void {
    app(TenantContext::class)->eachBusiness(fn ($business) => Artisan::call('baileys:prune-media', [
        '--orphans' => true,
        '--business' => $business->id,
    ]));
})->dailyAt('03:15')->name('prune-baileys-media')->withoutOverlapping();
