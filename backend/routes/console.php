<?php

use App\Models\Proxy;
use App\Jobs\CheckProxyJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CRON job proxy checker
Schedule::call(function () {
    Proxy::all()->each(function ($proxy) {
        CheckProxyJob::dispatch($proxy);
    });
})->everyFiveMinutes();
