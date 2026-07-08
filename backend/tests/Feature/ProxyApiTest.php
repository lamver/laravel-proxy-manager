<?php

namespace Tests\Feature;

use App\Models\Proxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProxyApiTest extends TestCase
{
    // Очищает базу данных тестового окружения перед каждым запуском теста
    use RefreshDatabase;

    /**
     * Тест: Успешное добавление рабочего прокси
     */
    public function test_can_create_and_automatically_activate_working_proxy(): void
    {
        // Имитируем, что внешний API проверки IP вернул JSON с IP-адресом прокси
        Http::fake([
            'https://ipify.org*' => Http::response(['ip' => '185.22.44.11'], 200),
        ]);

        $payload = [
            'ip' => '185.22.44.11',
            'port' => 8080,
            'type' => 'http',
            'username' => 'user',
            'password' => 'pass'
        ];

        // Отправляем POST запрос на создание
        $response = $this->postJson('/api/proxies', $payload);

        // Проверяем статус ответа API (201 Created)
        $response->assertStatus(201);

        // Проверяем, что в JSON-ответе вернулся статус 'active'
        $response->assertJsonFragment(['status' => 'active']);

        // Проверяем, что запись физически появилась в базе данных с нужным статусом
        $this->assertDatabaseHas('proxies', [
            'ip' => '185.22.44.11',
            'port' => 8080,
            'status' => 'active'
        ]);
    }

    /**
     * Тест: Добавление нерабочего прокси (API выдает ошибку)
     */
    public function test_proxy_marked_as_dead_if_checker_service_fails(): void
    {
        // Имитируем сбой сети или ошибку 503 на всех внешних сервисах
        Http::fake([
            '*' => Http::response('Service Unavailable', 503),
        ]);

        $payload = [
            'ip' => '192.168.1.99',
            'port' => 3128,
            'type' => 'socks5'
        ];

        $response = $this->postJson('/api/proxies', $payload);

        $response->assertStatus(201);
        $response->assertJsonFragment(['status' => 'dead']);

        $this->assertDatabaseHas('proxies', [
            'ip' => '192.168.1.99',
            'port' => 3128,
            'status' => 'dead'
        ]);
    }

    /**
     * Тест: Проверка работы нашего кастомного валидатора на уникальность IP+Порт
     */
    public function test_validation_fails_if_duplicate_ip_and_port_provided(): void
    {
        // Заранее создаем один прокси в пустой тестовой базе данных
        Proxy::create([
            'ip' => '95.10.20.30',
            'port' => 8080,
            'type' => 'http',
            'status' => 'unchecked'
        ]);

        // Пытаемся отправить точно такую же пару IP и Порт еще раз
        $payload = [
            'ip' => '95.10.20.30',
            'port' => 8080,
            'type' => 'https'
        ];

        $response = $this->postJson('/api/proxies', $payload);

        // Laravel должен вернуть ошибку валидации 422 Unprocessable Entity
        $response->assertStatus(422);

        // Проверяем, что в структуре ответа вернулись понятные ошибки для фронтенда
        $response->assertJsonValidationErrors(['ip']);
    }
}
