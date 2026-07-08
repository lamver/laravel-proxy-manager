<?php

namespace App\Services;

use App\Models\Proxy;
use Illuminate\Support\Facades\Http;
use Exception;

class ProxyCheckerService
{
    protected array $ipCheckers = [
        'https://ipify.org',
        'https://ipinfo.io',
        'https://ifconfig.me'
    ];

    public function check(Proxy $proxy): string
    {
       $status = 'dead';

        if ($proxy->username && $proxy->password) {
            $proxyString = "{$proxy->type}://{$proxy->username}:{$proxy->password}@{$proxy->ip}:{$proxy->port}";
        } else {
            $proxyString = "{$proxy->type}://{$proxy->ip}:{$proxy->port}";
        }

        foreach ($this->ipCheckers as $url) {
            try {
                $response = Http::timeout(4)
                    ->connectTimeout(2)
                    ->withOptions([
                        'proxy' => $proxyString,
                        'verify' => false,
                    ])
                    ->get($url);

                if ($response->successful()) {
                    $body = $response->text();

                    if (str_contains($body, $proxy->ip)) {
                        $status = 'active';
                        break;
                    }
                }
            } catch (Exception $e) {
                continue;
            }
        }

        $proxy->update([
            'status' => $status,
            'last_checked_at' => now(),
        ]);

        return $status;
    }
}
