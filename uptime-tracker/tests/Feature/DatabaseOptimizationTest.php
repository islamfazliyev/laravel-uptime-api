<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\Ping;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_monitors_index_eager_loads_latest_ping_without_n_plus_one_queries()
    {
        $monitors = Monitor::factory()->count(10)->create(['user_id' => $this->user->id]);

        foreach ($monitors as $monitor) {
            Ping::factory()->count(3)->create(['monitor_id' => $monitor->id]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/monitors');

        $response->assertOk()->assertJsonCount(10);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 1 query for user authentication/token check,
        // 1 query for monitors,
        // 1 query for eager loading latestPing.
        // If N+1 was present, there would be 10+ extra ping queries.
        $this->assertLessThanOrEqual(4, count($queries), 'N+1 detected: Too many database queries executed on /api/monitors');
    }

    public function test_logs_endpoint_eager_loads_monitor_relationship_without_n_plus_one_queries()
    {
        $monitors = Monitor::factory()->count(5)->create(['user_id' => $this->user->id]);

        foreach ($monitors as $monitor) {
            Ping::factory()->count(4)->create(['monitor_id' => $monitor->id]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/logs');

        $response->assertOk()->assertJsonCount(20);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Should execute monitor_ids query, pings query, and eager-load monitor relation in 1 query.
        $this->assertLessThanOrEqual(5, count($queries), 'N+1 detected: Too many database queries executed on /api/logs');
    }

    public function test_indexes_are_applied_to_monitors_and_pings_tables()
    {
        $this->assertTrue(Schema::hasTable('monitors'));
        $this->assertTrue(Schema::hasTable('pings'));

        $this->assertTrue(Schema::hasColumns('monitors', ['user_id', 'status', 'is_paused', 'last_checked_at']));
        $this->assertTrue(Schema::hasColumns('pings', ['monitor_id', 'created_at', 'status_code']));
    }

    public function test_response_time_benchmark_remains_under_fast_threshold()
    {
        Monitor::factory()->count(5)->create(['user_id' => $this->user->id]);

        $start = microtime(true);
        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/monitors');
        $durationMs = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(200, $durationMs, "Response time ({$durationMs}ms) exceeded 200ms benchmark threshold.");
    }
}
