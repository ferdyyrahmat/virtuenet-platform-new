<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Predis\Response\Status;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_is_available(): void
    {
        $this->getJson('/health/live')
            ->assertOk();
    }

    public function test_readiness_endpoint_checks_required_dependencies(): void
    {
        config()->set('cache.default', 'array');
        config()->set('queue.default', 'sync');
        config()->set('session.driver', 'array');

        $this->getJson('/health/readiness')
            ->assertOk()
            ->assertJson(['status' => 'ready', 'checks' => ['database' => true]]);
    }

    public function test_readiness_accepts_predis_pong_status_object(): void
    {
        config()->set('cache.default', 'redis');
        config()->set('queue.default', 'sync');
        config()->set('session.driver', 'array');
        Redis::shouldReceive('connection->ping')->once()->andReturn(new Status('PONG'));

        $this->getJson('/health/readiness')
            ->assertOk()
            ->assertJson(['status' => 'ready', 'checks' => ['database' => true, 'redis' => true]]);
    }
}
