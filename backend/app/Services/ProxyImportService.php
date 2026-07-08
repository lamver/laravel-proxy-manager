<?php

namespace App\Services;

use App\Jobs\CheckProxyJob;
use App\Models\Proxy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

class ProxyImportService
{
    /**
     * Импортировать прокси из текстового файла
     * 
     * @param UploadedFile $file
     * @return array Массив со статистикой импорта и списком ошибок
     */
    public function importFromTxt(UploadedFile $file): array
    {
        $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $importedCount = 0;
        $errors = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (str_contains($line, '://')) {
                $line = str_replace('://', ':', $line);
            }
            $line = preg_replace('/\s+/', ' ', $line);

            $type = 'http';
            $username = null;
            $password = null;
            $ip = '';
            $port = 0;

            // --- IF IPv6 ---
            if (str_contains($line, '[') && str_contains($line, ']')) {
                // Pattern А: protocol:[ipv6]:port:user:pass
                if (preg_match('/^([a-z0-9]+):\[([^\]]+)\]:([0-9]+):([^:]+):(.+)$/i', $line, $matches)) {
                    $type     = strtolower($matches[1]);
                    $ip       = $matches[2]; // Чистый IP изнутри скобок
                    $port     = (int)$matches[3];
                    $username = $matches[4];
                    $password = $matches[5];
                }
                // Pattern Б: protocol:[ipv6]:port
                elseif (preg_match('/^([a-z0-9]+):\[([^\]]+)\]:([0-9]+)$/i', $line, $matches)) {
                    $type     = strtolower($matches[1]);
                    $ip       = $matches[2]; // Чистый IP изнутри скобок
                    $port     = (int)$matches[3];
                } else {
                    $errors[] = "Строка " . ($index + 1) . ": Неверный формат IPv6.";
                    continue;
                }
            }
            // --- IF IPv4 ---
            else {
                $parts = array_map('trim', explode(':', $line));
                $count = count($parts);

                if ($count === 3) {
                    $type = strtolower($parts[0]);
                    $ip   = $parts[1];
                    $port = (int)$parts[2];
                } elseif ($count === 5) {
                    $type     = strtolower($parts[0]);
                    $username = $parts[1];
                    $password = $parts[2];
                    $ip       = $parts[3];
                    $port     = (int)$parts[4];
                } else {
                    $errors[] = "Строка " . ($index + 1) . ": Неверный формат (ожидалось 3 или 5 элементов, получено {$count}).";
                    continue;
                }
            }

            if (!in_array($type, ['http', 'https', 'socks4', 'socks5'])) {
                $type = 'http';
            }

            $proxyData = [
                'ip'       => $ip,
                'port'     => $port,
                'type'     => $type,
                'username' => $username,
                'password' => $password,
            ];

            $validator = Validator::make($proxyData, [
                'ip'   => 'required|ip',
                'port' => 'required|integer|between:1,65535',
            ]);

            if ($validator->fails()) {
                $errors[] = "Строка " . ($index + 1) . ": Ошибка валидации IP или Порта ({$ip}:{$port}).";
                continue;
            }

            $exists = Proxy::where('ip', $ip)->where('port', $port)->exists();
            if ($exists) continue;

            $proxy = Proxy::create(array_merge($proxyData, ['status' => 'unchecked']));
            CheckProxyJob::dispatch($proxy);

            $importedCount++;
        }

        return [
            'imported' => $importedCount,
            'errors' => $errors
        ];
    }
}
