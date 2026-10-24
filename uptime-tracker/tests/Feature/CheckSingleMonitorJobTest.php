<?php

namespace Tests\Feature;

use App\Jobs\CheckSingleMonitorJob;
use App\Models\Monitor;
use App\Models\User;
use App\Notifications\UptimeAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CheckSingleMonitorJobTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_transient_failure_does_not_mark_monitor_down_when_retry_succeeds()
    {
        Notification::fake();

        $monitor = Monitor::factory()->create([
            'user_id' => $this->user->id,
            'url' => 'https://example.com',
            'status' => 'up',
            'max_response_time_ms' => 2000,
        ]);

        // First request fails (500), second request succeeds (200 OK)
        Http::fake([
            'https://example.com' => Http::sequence()
                ->push('Internal Server Error', 500)
                ->push('OK', 200),
        ]);

        $job = new CheckSingleMonitorJob($monitor);
        $job->retryDelaySeconds = 0;
        $job->handle();

        $this->assertEquals('up', $monitor->fresh()->status);
        Notification::assertNothingSent();
        Http::assertSentCount(2);
    }

    public function test_transient_timeout_does_not_mark_monitor_down_when_retry_succeeds()
    {
        Notification::fake();

        $monitor = Monitor::factory()->create([
            'user_id' => $this->user->id,
            'url' => 'https://example.com',
            'status' => 'up',
            'max_response_time_ms' => 2000,
        ]);

        // First request times out with exception, second request succeeds (200 OK)
        $attempts = 0;
        Http::fake([
            'https://example.com' => function () use (&$attempts) {
                $attempts++;
                if ($attempts === 1) {
                    throw new ConnectionException('Connection timed out');
                }
                return Http::response('OK', 200);
            },
        ]);

        $job = new CheckSingleMonitorJob($monitor);
        $job->retryDelaySeconds = 0;
        $job->handle();

        $this->assertEquals('up', $monitor->fresh()->status);
        Notification::assertNothingSent();
        $this->assertSame(2, $attempts);
    }

    public function test_persistent_failure_marks_monitor_down_and_sends_alert_after_second_failed_check()
    {
        Notification::fake();

        $monitor = Monitor::factory()->create([
            'user_id' => $this->user->id,
            'url' => 'https://example.com',
            'status' => 'up',
            'max_response_time_ms' => 2000,
        ]);

        // Both attempts fail (503 Service Unavailable)
        Http::fake([
            'https://example.com' => Http::sequence()
                ->push('Service Unavailable', 503)
                ->push('Service Unavailable', 503),
        ]);

        $job = new CheckSingleMonitorJob($monitor);
        $job->retryDelaySeconds = 0;
        $job->handle();

        $this->assertEquals('down', $monitor->fresh()->status);
        Notification::assertSentTo($monitor, UptimeAlert::class, function ($notification) {
            return $notification->isUp === false && $notification->statusCode === 503;
        });
        Http::assertSentCount(2);
    }

    public function test_immediate_success_does_not_trigger_retry()
    {
        Notification::fake();

        $monitor = Monitor::factory()->create([
            'user_id' => $this->user->id,
            'url' => 'https://example.com',
            'status' => 'up',
            'max_response_time_ms' => 2000,
        ]);

        Http::fake([
            'https://example.com' => Http::response('OK', 200),
        ]);

        $job = new CheckSingleMonitorJob($monitor);
        $job->handle();

        $this->assertEquals('up', $monitor->fresh()->status);
        Notification::assertNothingSent();
        Http::assertSentCount(1);
    }

    public function test_default_retry_delay_is_two_seconds()
    {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->id]);
        $job = new CheckSingleMonitorJob($monitor);

        $this->assertSame(2, $job->retryDelaySeconds);
    }

    public function test_discord_alert_is_dispatched_only_when_second_check_also_fails()
    {
        putenv('DISCORD_ALERT_WEBHOOK=https://discord.com/api/webhooks/test-webhook');

        $monitor = Monitor::factory()->create([
            'user_id' => $this->user->id,
            'url' => 'https://example.com',
            'status' => 'up',
            'max_response_time_ms' => 2000,
        ]);

        Http::fake([
            'https://example.com' => Http::sequence()
                ->push('Internal Server Error', 500)
                ->push('Internal Server Error', 500),
            'https://discord.com/api/webhooks/*' => Http::response('OK', 200),
        ]);

        $job = new CheckSingleMonitorJob($monitor);
        $job->retryDelaySeconds = 0;
        $job->handle();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'discord.com/api/webhooks')
                && str_contains($request['content'], 'WARNING')
                && str_contains($request['content'], 'Status Code: 500');
        });

        putenv('DISCORD_ALERT_WEBHOOK');
    }

    public function test_discord_alert_is_not_dispatched_when_transient_failure_recovers_on_retry()
    {
        putenv('DISCORD_ALERT_WEBHOOK=https://discord.com/api/webhooks/test-webhook');

        $monitor = Monitor::factory()->create([
            'user_id' => $this->user->id,
            'url' => 'https://example.com',
            'status' => 'up',
            'max_response_time_ms' => 2000,
        ]);

        Http::fake([
            'https://example.com' => Http::sequence()
                ->push('Internal Server Error', 500)
                ->push('OK', 200),
            'https://discord.com/api/webhooks/*' => Http::response('OK', 200),
        ]);

        $job = new CheckSingleMonitorJob($monitor);
        $job->retryDelaySeconds = 0;
        $job->handle();

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'discord.com/api/webhooks');
        });

        putenv('DISCORD_ALERT_WEBHOOK');
    }
}
