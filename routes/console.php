<?php

use App\Jobs\PublishCampaignPostJob;
use App\Models\CampaignPost;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Sweep scheduled campaign posts whose time has come and dispatch a publish job
 * for each one. Idempotent: the job re-checks state before posting.
 */
Schedule::call(function (): void {
    CampaignPost::duePublishing()
        ->limit(50)
        ->pluck('id')
        ->each(fn (int $id) => PublishCampaignPostJob::dispatch($id));
})->everyFiveMinutes()->name('publish-due-campaign-posts')->withoutOverlapping();

/**
 * Expire stored WhatsApp media. Without this, inbound media from every
 * connected client phone accumulates in storage/app/public/baileys/ forever.
 * Retention window: config('baileys.media.retention_days').
 */
Schedule::command('baileys:prune-media --orphans')
    ->dailyAt('03:15')
    ->name('prune-baileys-media')
    ->withoutOverlapping();

/**
 * Delete expired business data exports: each holds every customer's details.
 */
Schedule::command('business-exports:prune')
    ->dailyAt('03:30')
    ->name('prune-business-exports')
    ->withoutOverlapping();
