<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Synchronize latest disposable/throwaway domains nightly from community feed
Schedule::command('deliverability:sync-disposable-domains')->dailyAt('02:00');

// Clean up inactive contacts & dispatch last-chance re-engagement emails nightly
Schedule::command('mail:cleanup-inactive')->dailyAt('03:00');

// Dispatch scheduled campaigns whose delivery time has arrived
Schedule::command('campaigns:send-scheduled')->everyMinute();
