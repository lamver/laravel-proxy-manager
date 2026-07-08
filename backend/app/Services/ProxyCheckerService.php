<?php

namespace App\Services;

use App\Models\Proxy;

/**
 * ProxyCheckerService class
 */
class ProxyCheckerService
{
    /**
     * check proxy function
     *
     * @param Proxy $proxy
     * @return string
     */
    public function check(Proxy $proxy): string
    {
        $status = 'dead';

        $envTargets = env('PROXY_CHECK_TARGETS', 'https://google.com');
        $targets = array_map('trim', explode(',', $envTargets));

        foreach ($targets as $url) {
            if (empty($url)) continue;

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            curl_setopt($ch, CURLOPT_PROXY, $proxy->ip);
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxy->port);

            if ($proxy->type === 'socks5') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
            } elseif ($proxy->type === 'socks4') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
            } else {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            }

            if ($proxy->username && $proxy->password) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$proxy->username}:{$proxy->password}");
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($ch);

            if ($curlErrno === 0 && ($httpCode === 200 || $httpCode === 204)) {
                $status = 'active';
                break;
            }
        }

        $proxy->update([
            'status' => $status,
            'last_checked_at' => now(),
        ]);

        return $status;
    }
}
