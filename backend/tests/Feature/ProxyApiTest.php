<?php

namespace Tests\Feature;

use App\Models\Proxy;
use App\Jobs\CheckProxyJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * ProxyApiTest class
 */
class ProxyApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * test_can_create_proxy_and_dispatches_job function
     *
     * @return void
     */
    public function test_can_create_proxy_and_dispatches_job(): void
    {
        Bus::fake();

        $payload = [
            'ip' => '185.22.44.11',
            'port' => 8080,
            'type' => 'http',
            'username' => 'user',
            'password' => 'pass'
        ];

        $response = $this->postJson('/api/proxies', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('proxies', [
            'ip' => '185.22.44.11',
            'port' => 8080,
            'status' => 'unchecked'
        ]);

        Bus::assertDispatched(CheckProxyJob::class);
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
