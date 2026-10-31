<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\Ping;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanUptimeTest extends TestCase
{
    use RefreshDatabase;

    protected Monitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->monitor = Monitor::factory()->create(['user_id' => $user->id]);
    }

    public function test_uptime_clean_deletes_records_older_than_seven_days()
    {
        // 8 days old ping -> should be pruned
        $oldPing = Ping::factory()->create([
            'monitor_id' => $this->monitor->id,
            'created_at' => now()->subDays(8),
        ]);

        // 3 days old ping -> should be kept
        $recentPing = Ping::factory()->create([
            'monitor_id' => $this->monitor->id,
            'created_at' => now()->subDays(3),
        ]);

        $this->artisan('uptime:clean')
            ->expectsOutput('Successfully pruned 1 ping record(s) older than 7 days.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('pings', ['id' => $oldPing->id]);
        $this->assertDatabaseHas('pings', ['id' => $recentPing->id]);
    }

    public function test_uptime_clean_with_custom_days_retention()
    {
        // 35 days old ping -> should be pruned with --days=30
        $veryOldPing = Ping::factory()->create([
            'monitor_id' => $this->monitor->id,
            'created_at' => now()->subDays(35),
        ]);

        // 20 days old ping -> should be kept with --days=30
        $twentyDaysOldPing = Ping::factory()->create([
            'monitor_id' => $this->monitor->id,
            'created_at' => now()->subDays(20),
        ]);

        $this->artisan('uptime:clean --days=30')
            ->expectsOutput('Successfully pruned 1 ping record(s) older than 30 days.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('pings', ['id' => $veryOldPing->id]);
        $this->assertDatabaseHas('pings', ['id' => $twentyDaysOldPing->id]);
    }

    public function test_ping_model_is_prunable_via_model_prune_command()
    {
        // 10 days old ping
        $oldPing = Ping::factory()->create([
            'monitor_id' => $this->monitor->id,
            'created_at' => now()->subDays(10),
        ]);

        // 1 day old ping
        $newPing = Ping::factory()->create([
            'monitor_id' => $this->monitor->id,
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('model:prune', ['--model' => [Ping::class]])
            ->assertSuccessful();

        $this->assertDatabaseMissing('pings', ['id' => $oldPing->id]);
        $this->assertDatabaseHas('pings', ['id' => $newPing->id]);
    }

    public function test_uptime_clean_is_registered_to_run_daily_in_schedule()
    {
        $schedule = app()->make(Schedule::class);

        $events = collect($schedule->events())->filter(function ($event) {
            return str_contains($event->command, 'uptime:clean');
        });

        $this->assertNotEmpty($events, 'uptime:clean command is not scheduled');

        $cleanEvent = $events->first();
        // Daily expression in cron is '0 0 * * *'
        $this->assertEquals('0 0 * * *', $cleanEvent->expression);
    }
}
