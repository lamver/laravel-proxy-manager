<?php

namespace App\Services;

use App\Models\Proxy;
use Illuminate\Support\Facades\Http;
use Exception;

class ProxyCheckerService
{
    /**
     * Список надежных URL для проверки прокси
     */
    protected array $targets = [
        'https://google.com',
        'https://1.1.1.1',
        'https://httpbin.org'
    ];

    /**
     * Проверить работоспособность прокси-сервера
     */
    public function check(Proxy $proxy): string
    {
        $proxyUrl = "{$proxy->type}://{$proxy->ip}:{$proxy->port}";
        $status = 'dead';

        try {
            $request = Http::timeout(5)->connectTimeout(3);

            if ($proxy->username && $proxy->password) {
                $request->withOptions([
                    'proxy' => [
                        $proxy->type => "{$proxy->type}://{$proxy->username}:{$proxy->password}@{$proxy->ip}:{$proxy->port}"
                    ]
                ]);
            } else {
                $request->withOptions(['proxy' => $proxyUrl]);
            }

            foreach ($this->targets as $target) {
                $response = $request->get($target);
                if ($response->successful()) {
                    $status = 'active';
                    break;
                }
            }
        } catch (Exception $e) {
            $status = 'dead';
        }
        
        $proxy->update([
            'status' => $status,
            'last_checked_at' => now(),
        ]);

        return $status;
    }
}
