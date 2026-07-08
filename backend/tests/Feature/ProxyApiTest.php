<?php

namespace Tests\Feature;

use App\Models\Proxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ProxyApiTest class
 */
class ProxyApiTest extends TestCase
{
    // Очищает базу данных тестового окружения перед каждым запуском теста
    use RefreshDatabase;

    /**
     * test test_can_create_and_automatically_activate_working_proxy function
     *
     * @return void
     */
    public function test_can_create_and_automatically_activate_working_proxy(): void
    {
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

        $response = $this->postJson('/api/proxies', $payload);

        $response->assertStatus(201);

        $response->assertJsonFragment(['status' => 'active']);

        $this->assertDatabaseHas('proxies', [
            'ip' => '185.22.44.11',
            'port' => 8080,
            'status' => 'active'
        ]);
    }

    /**
     * test_proxy_marked_as_dead_if_checker_service_fails function
     *
     * @return void
     */
    public function test_proxy_marked_as_dead_if_checker_service_fails(): void
    {
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
     * test_validation_fails_if_duplicate_ip_and_port_provided function
     *
     * @return void
     */
    public function test_validation_fails_if_duplicate_ip_and_port_provided(): void
    {
        Proxy::create([
            'ip' => '95.10.20.30',
            'port' => 8080,
            'type' => 'http',
            'status' => 'unchecked'
        ]);

        $payload = [
            'ip' => '95.10.20.30',
            'port' => 8080,
            'type' => 'https'
        ];

        $response = $this->postJson('/api/proxies', $payload);

        $response->assertStatus(422);

        $response->assertJsonValidationErrors(['ip']);
    }
}
