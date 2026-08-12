<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class IpAddressAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_records_request_ip_and_user_agent(): void
    {
        $user = User::factory()->create();

        $this->app->instance('request', Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.42',
            'HTTP_USER_AGENT' => 'phpunit-user-agent',
        ]));

        audit_log('Test ip capture', 'test', 'system', [], $user);

        $activity = Activity::latest()->first();

        $this->assertSame('203.0.113.42', $activity->properties['ip_address']);
        $this->assertSame('phpunit-user-agent', $activity->properties['user_agent']);
    }

    public function test_audit_log_respects_explicit_ip_address(): void
    {
        $user = User::factory()->create();

        $this->app->instance('request', Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.42',
        ]));

        audit_log('Test explicit ip', 'test', 'system', ['ip_address' => '1.2.3.4', 'foo' => 'bar'], $user);

        $activity = Activity::latest()->first();

        $this->assertSame('1.2.3.4', $activity->properties['ip_address']);
        $this->assertSame('bar', $activity->properties['foo']);
    }
}