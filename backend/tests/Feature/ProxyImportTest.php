<?php

namespace Tests\Feature;

use App\Models\Proxy;
use App\Jobs\CheckProxyJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProxyImportTest extends TestCase
{
    // Очищает базу данных перед запуском тестов импорта
    use RefreshDatabase;

    /**
     * Тестирование импорта всех возможных форматов прокси из одного файла
     */
    public function test_can_import_various_proxy_formats_from_file(): void
    {
        // Блокируем реальное выполнение очередей, проверяем только факт их создания
        Bus::fake();

        // Создаем один прокси-дубликат заранее, чтобы проверить защиту от повторов
        Proxy::create([
            'ip' => '72.195.34.42',
            'port' => 4145,
            'type' => 'socks5',
            'status' => 'active'
        ]);

        // Формируем контент файла, содержащий абсолютно все кейсы
        $fileContent = implode("\n", [
            "socks5://208.102.51.6:58208",                  // Кейс 1: IPv4 URL без авторизации
            "http:manager3:pass12345:95.213.132.50:3128",    // Кейс 2: IPv4 классический с авторизацией
            "socks4:185.22.44.11:8080",                      // Кейс 3: IPv4 классический без авторизации
            "socks5://[2001:db8:85a3::8a2e:370:7334]:58208", // Кейс 4: IPv6 URL без авторизации
            "http:[2a02:6b8:b010:7007::1]:3128:user:pass",   // Кейс 5: IPv6 классический с авторизацией
            "socks5://72.195.34.42:4145",                    // Кейс 6: ДУБЛИКАТ -> должен быть пропущен
            "invalid-garbage-line-here",                     // Кейс 7: МУСОР -> должен быть пропущен
            "http://999.999.999",                    // Кейс 8: НЕВАЛИДНЫЙ IP -> должен быть пропущен
        ]);

        // Имитируем загрузку .txt файла в памяти
        $file = UploadedFile::fake()->createWithContent('proxies.txt', $fileContent);

        // Отправляем POST запрос на эндпоинт импорта сервиса
        $response = $this->postJson('/api/proxies/import', [
            'file' => $file
        ]);

        // 1. Проверка HTTP-статуса ответа
        $response->assertStatus(200);

        // Ожидаем ровно 5 успешно импортированных прокси (Кейсы 1, 2, 3, 4, 5)
        $response->assertJsonFragment(['imported' => 5]);

        // Проверяем, что в массиве пропущенных строк (skipped_errors) зафиксированы Кейсы 7 и 8
        $response->assertJsonStructure(['skipped_errors']);
        $this->assertCount(2, $response->json('skipped_errors'));

        // 2. Проверка базы данных для каждого формата

        // Кейс 1: IPv4 URL без авторизации
        $this->assertDatabaseHas('proxies', [
            'ip' => '208.102.51.6',
            'port' => 58208,
            'type' => 'socks5',
            'username' => null
        ]);

        // Кейс 2: IPv4 с авторизацией
        $this->assertDatabaseHas('proxies', [
            'ip' => '95.213.132.50',
            'port' => 3128,
            'type' => 'http',
            'username' => 'manager3',
            'password' => 'pass12345'
        ]);

        // Кейс 3: IPv4 стандартный без авторизации
        $this->assertDatabaseHas('proxies', [
            'ip' => '185.22.44.11',
            'port' => 8080,
            'type' => 'socks4'
        ]);

        // Кейс 4: IPv6 URL без авторизации
        $this->assertDatabaseHas('proxies', [
            'ip' => '2001:db8:85a3::8a2e:370:7334',
            'port' => 58208,
            'type' => 'socks5'
        ]);

        // Кейс 5: IPv6 с авторизацией
        $this->assertDatabaseHas('proxies', [
            'ip' => '2a02:6b8:b010:7007::1',
            'port' => 3128,
            'type' => 'http',
            'username' => 'user',
            'password' => 'pass'
        ]);

        // Кейс 6: Проверка уникальности (в БД должна остаться только одна запись дубликата)
        $this->assertEquals(1, Proxy::where('ip', '72.195.34.42')->where('port', 4145)->count());

        // 3. Проверка очередей задач
        // Убеждаемся, что для 5 успешно добавленных прокси Laravel создал фоновые джобы CheckProxyJob
        Bus::assertDispatched(CheckProxyJob::class, 5);
    }
}
