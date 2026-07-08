<?php

use App\Models\Proxy;
use App\Services\ProxyCheckerService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CRON
Schedule::call(function (ProxyCheckerService $proxyChecker) {
    Proxy::all()->each(function ($proxy) use ($proxyChecker) {
        $proxyChecker->check($proxy);
    });
})->everyFiveMinutes();
