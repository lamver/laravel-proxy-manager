<?php

namespace App\Jobs;

use App\Models\Proxy;
use App\Services\ProxyCheckerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckProxyJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     *
     * @param Proxy $proxy
     */
    public function __construct(protected Proxy $proxy)
    {
        //
    }

    /**
     * Execute the job.
     *
     * @param ProxyCheckerService $proxyChecker
     * @return void
     */
    public function handle(ProxyCheckerService $proxyChecker): void
    {
        $proxyChecker->check($this->proxy);
    }
}
