<?php

namespace App\Services;

use App\Models\Proxy;
use Exception;
use Illuminate\Support\Facades\Http;


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

        // 1. Получаем строку из .env и разбиваем её по запятой в массив
        $envTargets = env('PROXY_CHECK_TARGETS', 'https://datahunter.store');
        $targets = array_map('trim', explode(',', $envTargets));

        // 2. Перебираем сайты по очереди. Если один недоступен, идем к следующему
        foreach ($targets as $url) {
            if (empty($url)) continue;

            $ch = curl_init();

            // Базовые настройки cURL
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

            // Игнорируем проблемы с SSL-сертификатами
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            // Настройки прокси
            curl_setopt($ch, CURLOPT_PROXY, $proxy->ip);
            curl_setopt($ch, CURLOPT_PROXYPORT, $proxy->port);

            // Тип протокола
            if ($proxy->type === 'socks5') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5_HOSTNAME);
            } elseif ($proxy->type === 'socks4') {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS4);
            } else {
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            }

            // Авторизация
            if ($proxy->username && $proxy->password) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$proxy->username}:{$proxy->password}");
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($ch);

            curl_close($ch);

            // Если cURL успешно пробил туннель до текущего URL
            if ($curlErrno === 0 && ($httpCode === 200 || $httpCode === 204)) {
                $status = 'active';
                break; // Успех! Выходим из цикла, остальные сайты проверять не нужно
            }
        }

        // 3. Записываем статус в базу данных
        $proxy->update([
            'status' => $status,
            'last_checked_at' => now(),
        ]);

        return $status;
    }
}
