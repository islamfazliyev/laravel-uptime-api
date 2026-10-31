<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_pause_monitor()
    {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->id, 'is_paused' => false]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/monitors/{$monitor->id}/pause");

        $response->assertStatus(200);
        $this->assertTrue($monitor->fresh()->is_paused);
    }

    public function test_can_resume_monitor()
    {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->id, 'is_paused' => true]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/monitors/{$monitor->id}/resume");

        $response->assertStatus(200);
        $this->assertFalse($monitor->fresh()->is_paused);
    }

    public function test_can_create_monitor_with_ssl_check_enabled()
    {
        $data = [
            'name' => 'My HTTPS Site',
            'url' => 'https://example.com',
            'check_interval' => 60,
            'certificate_check_enabled' => true
        ];

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/monitors', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('monitors', [
            'url' => 'https://example.com',
            'certificate_check_enabled' => 1
        ]);
    }

    public function test_user_can_list_monitors()
    {
        Monitor::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson('/api/monitors');

        $response->assertStatus(200)
            ->assertJsonCount(3);
    }

    public function test_cannot_create_monitor_with_invalid_url()
    {
        $data = [
            'name' => 'Bad Site',
            'url' => 'not-a-valid-url',
            'check_interval' => 5,
        ];

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/monitors', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_user_can_view_monitor_stats_with_uptime_percentage()
    {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->id]);

        \App\Models\Ping::create([
            'monitor_id' => $monitor->id,
            'status_code' => 200,
            'response_time_ms' => 150,
            'created_at' => now()->subHours(2),
        ]);
        \App\Models\Ping::create([
            'monitor_id' => $monitor->id,
            'status_code' => 500,
            'response_time_ms' => 200,
            'created_at' => now()->subHours(1),
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->getJson("/api/monitors/{$monitor->id}/stats");

        $response->assertStatus(200)
            ->assertJson([
                'uptime_24h' => 50,
                'total_checks_24h' => 2,
            ])
            ->assertJsonStructure(['uptime_24h', 'total_checks_24h', 'recent']);
    }

    public function test_user_can_trigger_manual_check_all()
    {
        \Illuminate\Support\Facades\Queue::fake();

        Monitor::factory()->count(2)->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson('/api/monitors/check');

        $response->assertStatus(202)
            ->assertJson([
                'message' => 'Checks queued',
                'count' => 2,
            ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\CheckSingleMonitorJob::class, 2);
    }

    public function test_user_can_trigger_manual_check_single()
    {
        \Illuminate\Support\Facades\Queue::fake();

        $monitor = Monitor::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')->postJson("/api/monitors/{$monitor->id}/check");

        $response->assertStatus(202)
            ->assertJson([
                'message' => 'Check queued',
                'monitor_id' => $monitor->id,
            ]);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\CheckSingleMonitorJob::class, 1);
    }

    public function test_user_can_delete_monitor()
    {
        $monitor = Monitor::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')->deleteJson("/api/monitors/{$monitor->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Monitor Deleted']);

        $this->assertDatabaseMissing('monitors', ['id' => $monitor->id]);
    }

    public function test_user_can_update_monitor()
    {
        $monitor = Monitor::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
            'url' => 'https://original.com',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')->putJson("/api/monitors/{$monitor->id}", [
            'name' => 'Updated Name',
            'url' => 'https://updated.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Monitor updated successfully']);

        $this->assertEquals('Updated Name', $monitor->fresh()->name);
        $this->assertEquals('https://updated.com', $monitor->fresh()->url);
    }
}
