<?php

namespace Tests\Unit;

use App\Models\Proxy;
use App\Services\ProxyCheckerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProxyLiveCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * test_live_proxy_checking function
     *
     * @return void
     */
    public function test_live_proxy_checking(): void
    {
        $checker = app(ProxyCheckerService::class);

        $proxy = Proxy::create([
            'ip' => env('TEST_PROXY_IP'),
            'port' => (int) env('TEST_PROXY_PORT', 8080),
            'type' => env('TEST_PROXY_TYPE', 'http'),
            'username' => env('TEST_PROXY_USER'),
            'password' => env('TEST_PROXY_PASS'),
            'status' => 'unchecked'
        ]);

        $status = $checker->check($proxy);

        $this->assertContains($status, ['active', 'dead']);
    }

    /**
     * test_raw_proxy_connection function
     *
     * @return void
     */
    public function test_raw_proxy_connection(): void
    {
        $ip = env('TEST_PROXY_IP');
        $port = (int) env('TEST_PROXY_PORT', 8080);
        $type = env('TEST_PROXY_TYPE', 'http');
        $username = env('TEST_PROXY_USER');
        $password = env('TEST_PROXY_PASS');

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.datahunter.store/api/v3/tools/check/device");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        curl_setopt($ch, CURLOPT_PROXY, $ip);
        curl_setopt($ch, CURLOPT_PROXYPORT, $port);

        if ($type === 'socks5') {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
        } elseif ($type === 'socks4') {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
        } else {
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        }

        if ($username && $password) {
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$username}:{$password}");
            curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
        }

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlErrno === 0) {
            echo "[РЕЗУЛЬТАТ] HTTP-код ответа удаленного сервера: " . $httpCode . "\n";
            echo "==================================================\n\n";
            echo "[РЕЗУЛЬТАТ] Начало ответа от сервера:\n";
            echo "--------------------------------------------------\n";
            echo substr($response, 0, 250) . "\n";
            echo "--------------------------------------------------\n";
            echo "==================================================\n\n";

            $this->assertTrue(true);
        } else {
            // fail() принудительно прокинет текст ошибки сквозь буфер Laravel
            $this->fail(
                "ошибка либы libcurl внутри Docker!\n" .
                    "[Код cURL]: " . $curlErrno . "\n" .
                    "[Текст ошибки]: " . $curlError . "\n" .
                    "[HTTP код ответа]: " . $httpCode
            );
        }
    }
}
